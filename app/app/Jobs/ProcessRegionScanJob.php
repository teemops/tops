<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\AwsSecurityScanner;
use App\Services\RulesEngine\RulesEngine;
use App\Services\RulesEngine\FindingsEngine;
use App\Services\RulesEngine\ConditionEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRegionScanJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Scan $scan,
        public string $region
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Reload scan to ensure we have latest data
        $this->scan->refresh();

        // Check if scan was cancelled
        if ($this->scan->status === 'cancelled') {
            Log::info('Scan cancelled, skipping region processing', [
                'scan_id' => $this->scan->id,
                'region' => $this->region,
            ]);
            return;
        }

        try {
            // Get AWS account
            $awsAccount = $this->scan->awsAccount;

            if (!$awsAccount || $awsAccount->status !== 'completed') {
                throw new \Exception('AWS account is not active');
            }

            // Get IAM role ARN and external ID
            $roleArn = $awsAccount->iam_role_arn;
            $externalId = $awsAccount->external_id;

            if (!$roleArn || !$externalId) {
                throw new \Exception('AWS account missing IAM role ARN or external ID');
            }

            // Create scanner for assuming role
            $scanner = new AwsSecurityScanner($roleArn, $externalId, $this->region);
            $credentials = $scanner->assumeRole();

            // Phase 1: Execute EC2 scan using RulesEngine (data collection)
            $rulesEngine = new RulesEngine();
            try {
                $rulesEngine->executeScan($this->scan, 'ec2', $credentials, $this->region);
                Log::info('EC2 region scan data collection completed', [
                    'scan_id' => $this->scan->id,
                    'region' => $this->region,
                ]);
            } catch (\Exception $e) {
                Log::error('EC2 region scan data collection failed', [
                    'scan_id' => $this->scan->id,
                    'region' => $this->region,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Phase 2: Evaluate findings using FindingsEngine
            $conditionEvaluator = new ConditionEvaluator();
            $findingsEngine = new FindingsEngine($conditionEvaluator);
            
            try {
                // Evaluate findings using basic ruleset
                $findingsEngine->evaluateScan($this->scan, ['basic']);
                
                Log::info('EC2 region scan findings evaluation completed', [
                    'scan_id' => $this->scan->id,
                    'region' => $this->region,
                ]);
            } catch (\Exception $e) {
                Log::error('EC2 region scan findings evaluation failed', [
                    'scan_id' => $this->scan->id,
                    'region' => $this->region,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the scan if findings evaluation fails
            }

            Log::info('Region scan completed', [
                'scan_id' => $this->scan->id,
                'region' => $this->region,
            ]);

            // Check if all regions are complete and mark scan as completed if so
            $this->scan->refresh();
            $this->scan->checkAndMarkEc2ScanComplete();
        } catch (\Exception $e) {
            Log::error('Region scan failed', [
                'scan_id' => $this->scan->id,
                'region' => $this->region,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Region scan job failed after all retries', [
            'scan_id' => $this->scan->id,
            'region' => $this->region,
            'error' => $exception->getMessage(),
        ]);

        // Don't mark entire scan as failed if one region fails
        // Individual region failures are logged but don't fail the entire scan
    }
}
