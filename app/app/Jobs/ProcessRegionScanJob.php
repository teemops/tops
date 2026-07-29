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
        public string $region,
        public string $service = 'ec2'
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

            // Phase 1: Execute region-based scan using RulesEngine (data collection)
            //
            // Skip collection when this (service, region) already has details. SQS is
            // at-least-once and this job retries three times on failure, so without the
            // guard a transient error partway through re-runs the region from the start
            // and writes every row a second time — there is no unique constraint on
            // scan_details to catch it. ProcessAuditScanJob has always done this for
            // global services; region jobs were the gap.
            //
            // The check comes before assuming the role so a redelivered job costs nothing
            // at AWS. Phases 2 and 3 below still run: a redelivery must be able to carry
            // the scan to completion even when it has no new data to add.
            if ($this->hasAlreadyCollected()) {
                Log::info('Skipping data collection, this service/region already has scan_details', [
                    'scan_id' => $this->scan->id,
                    'service' => $this->service,
                    'region' => $this->region,
                ]);
            } else {
                $scanner = new AwsSecurityScanner($roleArn, $externalId, $this->region);
                $credentials = $scanner->assumeRole();

                try {
                    (new RulesEngine())->executeScan($this->scan, $this->service, $credentials, $this->region);
                    Log::info("{$this->service} region scan data collection completed", [
                        'scan_id' => $this->scan->id,
                        'service' => $this->service,
                        'region' => $this->region,
                    ]);
                } catch (\Exception $e) {
                    Log::error("{$this->service} region scan data collection failed", [
                        'scan_id' => $this->scan->id,
                        'service' => $this->service,
                        'region' => $this->region,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            // Phase 2: Evaluate findings using FindingsEngine
            $conditionEvaluator = new ConditionEvaluator();
            $findingsEngine = new FindingsEngine($conditionEvaluator);
            
            try {
                // Evaluate findings using the scan's ruleset(s) (defaults to basic)
                $findingsEngine->evaluateScan($this->scan, $this->scan->rulesets ?? ['basic']);
                
                Log::info("{$this->service} region scan findings evaluation completed", [
                    'scan_id' => $this->scan->id,
                    'service' => $this->service,
                    'region' => $this->region,
                ]);
            } catch (\Exception $e) {
                Log::error("{$this->service} region scan findings evaluation failed", [
                    'scan_id' => $this->scan->id,
                    'service' => $this->service,
                    'region' => $this->region,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the scan if findings evaluation fails
            }

            Log::info('Region scan completed', [
                'scan_id' => $this->scan->id,
                'service' => $this->service,
                'region' => $this->region,
            ]);

            // Check if all regions are complete and mark scan as completed if so
            $this->scan->refresh();
            $this->scan->checkAndMarkRegionBasedScanComplete();
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
     * Whether this (service, region) pair already has collected data for this scan.
     *
     * Matches the pair the completion accounting counts in
     * Scan::checkAndMarkRegionBasedScanComplete(), so a redelivered job still reports as
     * done without re-collecting.
     */
    private function hasAlreadyCollected(): bool
    {
        return $this->scan->details()
            ->where('service', $this->service)
            ->where('region', $this->region)
            ->exists();
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
