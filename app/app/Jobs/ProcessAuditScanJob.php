<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\AwsSecurityScanner;
use App\Services\ScanTypesService;
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
            $hasRdsScan = false;
            $regionBasedServices = ScanTypesService::getRegionBased();

            foreach ($scanTypes as $scanType) {
                if (ScanTypesService::isRegionBased($scanType)) {
                    // For EC2 and RDS, get regions and dispatch region-specific jobs
                    if ($scanType === 'ec2') {
                        $hasEc2Scan = true;
                    } elseif ($scanType === 'rds') {
                        $hasRdsScan = true;
                    }
                    
                    $regions = $scanner->getAvailableRegions($credentials);

                    // Store expected regions count for completion tracking
                    // If multiple region-based services, use the max count
                    $currentExpected = $this->scan->expected_regions_count ?? 0;
                    $this->scan->update([
                        'expected_regions_count' => max($currentExpected, count($regions)),
                    ]);

                    foreach ($regions as $region) {
                        ProcessRegionScanJob::dispatch($this->scan, $region, $scanType)
                            ->onConnection('sqs-audit-region');
                    }

                    Log::info("{$scanType} scan dispatched to regions", [
                        'scan_id' => $this->scan->id,
                        'service' => $scanType,
                        'regions_count' => count($regions),
                    ]);
                } else {
                    // Execute scan for non-region-based services (IAM, S3)
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

            $hasRegionBasedScan = $hasEc2Scan || $hasRdsScan;
            
            Log::info('All scan types processed, starting findings evaluation', [
                'scan_id' => $this->scan->id,
                'has_ec2' => $hasEc2Scan,
                'has_rds' => $hasRdsScan,
                'has_region_based' => $hasRegionBasedScan,
                'scan_types' => $scanTypes,
            ]);

            // Phase 2: Evaluate findings using FindingsEngine (only for non-region-based scans)
            Log::info('Starting findings evaluation phase', [
                'scan_id' => $this->scan->id,
                'has_ec2' => $hasEc2Scan,
                'has_rds' => $hasRdsScan,
                'has_region_based' => $hasRegionBasedScan,
            ]);

            if (!$hasRegionBasedScan) {
                Log::info('Evaluating findings for non-EC2 scan', [
                    'scan_id' => $this->scan->id,
                ]);

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
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Don't fail the scan if findings evaluation fails
                } catch (\Throwable $e) {
                    // Catch any fatal errors too
                    Log::error('Findings evaluation failed with fatal error', [
                        'scan_id' => $this->scan->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                // Mark scan as completed (ensure this happens even if findings evaluation failed)
                // This MUST happen regardless of what happened above
                Log::info('Attempting to mark scan as completed', [
                    'scan_id' => $this->scan->id,
                ]);

                try {
                    $this->scan->refresh(); // Reload to ensure we have latest status
                    
                    if ($this->scan->status === 'running') {
                        $updateResult = $this->scan->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);

                        if (!$updateResult) {
                            Log::error('Scan update returned false', [
                                'scan_id' => $this->scan->id,
                            ]);
                        }

                        // Update AWS account last scan time
                        $awsAccount->refresh();
                        $awsAccount->update([
                            'last_scan_at' => now(),
                        ]);

                        // Verify the update worked
                        $this->scan->refresh();
                        Log::info('Scan marked as completed successfully', [
                            'scan_id' => $this->scan->id,
                            'status' => $this->scan->status,
                            'completed_at' => $this->scan->completed_at,
                        ]);
                    } else {
                        Log::warning('Scan status is not running, skipping completion update', [
                            'scan_id' => $this->scan->id,
                            'current_status' => $this->scan->status,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to mark scan as completed', [
                        'scan_id' => $this->scan->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Re-throw to ensure the job fails and can retry
                    throw $e;
                } catch (\Throwable $e) {
                    Log::error('Failed to mark scan as completed (fatal error)', [
                        'scan_id' => $this->scan->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            } else {
                // For region-based scans (EC2, RDS), we'll track completion via region jobs
                // The scan will be marked as completed when all regions are done
                Log::info('Scan with region-based service - waiting for region scans to complete', [
                    'scan_id' => $this->scan->id,
                    'has_ec2' => $hasEc2Scan,
                    'has_rds' => $hasRdsScan,
                ]);
            }

            Log::info('Scan processing completed', [
                'scan_id' => $this->scan->id,
                'has_ec2' => $hasEc2Scan,
                'has_rds' => $hasRdsScan,
                'has_region_based' => $hasRegionBasedScan,
            ]);
        } catch (\Exception $e) {
            Log::error('Scan failed', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Check if scan has data but failed during findings evaluation
            // In that case, mark as completed instead of failed
            $this->scan->refresh();
            $hasScanDetails = $this->scan->details()->exists();
            
            if ($hasScanDetails && !$hasRegionBasedScan && str_contains($e->getMessage(), 'Findings evaluation')) {
                // If we have scan details and it's not a region-based scan, mark as completed
                // The findings evaluation failure shouldn't fail the entire scan
                Log::warning('Marking scan as completed despite findings evaluation error', [
                    'scan_id' => $this->scan->id,
                    'error' => $e->getMessage(),
                ]);
                
                $this->scan->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'error_message' => 'Findings evaluation failed: ' . $e->getMessage(),
                ]);
                
                if ($this->scan->awsAccount) {
                    $this->scan->awsAccount->update([
                        'last_scan_at' => now(),
                    ]);
                }
            } else {
                // Update scan status to failed for other errors
                $this->scan->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);

                // Re-throw to trigger retry mechanism
                throw $e;
            }
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
