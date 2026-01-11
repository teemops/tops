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

class ProcessAuditScanJob implements ShouldQueue
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
        public Scan $scan
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
            Log::info('Scan cancelled, skipping processing', ['scan_id' => $this->scan->id]);
            return;
        }

        try {
            // Update scan status to running
            $this->scan->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

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

            // Get scan types
            $scanTypes = $this->scan->scan_types ?? [];

            if (empty($scanTypes)) {
                throw new \Exception('No scan types specified');
            }

            // Create scanner for assuming role
            $scanner = new AwsSecurityScanner($roleArn, $externalId);
            $credentials = $scanner->assumeRole();

            // Phase 1: Execute scans using RulesEngine (data collection)
            $rulesEngine = new RulesEngine();
            $hasEc2Scan = false;

            foreach ($scanTypes as $scanType) {
                if ($scanType === 'ec2') {
                    // For EC2, get regions and dispatch region-specific jobs
                    $hasEc2Scan = true;
                    $regions = $scanner->getAvailableRegions($credentials);

                    foreach ($regions as $region) {
                        ProcessRegionScanJob::dispatch($this->scan, $region)
                            ->onConnection('sqs-audit-region');
                    }

                    Log::info('EC2 scan dispatched to regions', [
                        'scan_id' => $this->scan->id,
                        'regions_count' => count($regions),
                    ]);
                } else {
                    // Execute scan for non-EC2 services (IAM, S3, RDS)
                    try {
                        $rulesEngine->executeScan($this->scan, $scanType, $credentials);
                        Log::info('Scan data collection completed', [
                            'scan_id' => $this->scan->id,
                            'service' => $scanType,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Scan data collection failed', [
                            'scan_id' => $this->scan->id,
                            'service' => $scanType,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with other services
                    }
                }
            }

            // Phase 2: Evaluate findings using FindingsEngine (only for non-EC2 scans)
            if (!$hasEc2Scan) {
                $conditionEvaluator = new ConditionEvaluator();
                $findingsEngine = new FindingsEngine($conditionEvaluator);
                
                try {
                    // Evaluate findings using basic ruleset (can be extended to support multiple rulesets)
                    $findingsEngine->evaluateScan($this->scan, ['basic']);
                    
                    Log::info('Findings evaluation completed', [
                        'scan_id' => $this->scan->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Findings evaluation failed', [
                        'scan_id' => $this->scan->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the scan if findings evaluation fails
                }

                // Mark scan as completed
                $this->scan->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Update AWS account last scan time
                $awsAccount->update([
                    'last_scan_at' => now(),
                ]);
            } else {
                // For EC2 scans, we'll track completion via region jobs
                // The scan will be marked as completed when all regions are done
                Log::info('Scan with EC2 type - waiting for region scans to complete', [
                    'scan_id' => $this->scan->id,
                ]);
            }

            Log::info('Scan processing completed', [
                'scan_id' => $this->scan->id,
                'has_ec2' => $hasEc2Scan,
            ]);
        } catch (\Exception $e) {
            Log::error('Scan failed', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update scan status to failed
            $this->scan->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
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
        Log::error('Audit scan job failed after all retries', [
            'scan_id' => $this->scan->id,
            'error' => $exception->getMessage(),
        ]);

        $this->scan->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => 'Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
        ]);
    }
}
