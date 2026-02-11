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

    private const RULESET_BASIC = 'basic';

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
        $this->scan->refresh();

        if ($this->scan->status === 'cancelled') {
            Log::info('Scan cancelled, skipping processing', ['scan_id' => $this->scan->id]);
            return;
        }

        $awsAccount = null;
        try {
            $this->scan->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            $awsAccount = $this->validateAndGetAwsAccount();
            $roleArn = $awsAccount->iam_role_arn;
            $externalId = $awsAccount->external_id;
            $scanTypes = $this->scan->scan_types ?? [];
            if (empty($scanTypes)) {
                throw new \Exception('No scan types specified');
            }

            $scanner = new AwsSecurityScanner($roleArn, $externalId);
            $credentials = $scanner->assumeRole();

            $rulesEngine = new RulesEngine();
            $hasEc2Scan = false;
            $hasRdsScan = false;

            foreach ($scanTypes as $scanType) {
                if (ScanTypesService::isRegionBased($scanType)) {
                    if ($scanType === 'ec2') {
                        $hasEc2Scan = true;
                    } elseif ($scanType === 'rds') {
                        $hasRdsScan = true;
                    }

                    $this->dispatchRegionScansForService($scanner, $credentials, $scanType);
                } else {
                    $this->runGlobalScanForService($rulesEngine, $scanType, $credentials);
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

            if (!$hasRegionBasedScan) {
                $this->evaluateFindings();
                $this->markScanCompleted($awsAccount);
            } else {
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
        } catch (\Throwable $e) {
            $this->handleScanFailure($e, $awsAccount);
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

    private function validateAndGetAwsAccount(): \App\Models\AwsAccount
    {
        $awsAccount = $this->scan->awsAccount;
        if (!$awsAccount || $awsAccount->status !== 'completed') {
            throw new \Exception('AWS account is not active');
        }
        if (!$awsAccount->iam_role_arn || !$awsAccount->external_id) {
            throw new \Exception('AWS account missing IAM role ARN or external ID');
        }
        return $awsAccount;
    }

    private function dispatchRegionScansForService(AwsSecurityScanner $scanner, array $credentials, string $scanType): void
    {
        $regions = $scanner->getAvailableRegions($credentials);
        $currentExpected = $this->scan->expected_regions_count ?? 0;
        $this->scan->update([
            'expected_regions_count' => $currentExpected + count($regions),
        ]);

        $regionConnection = config('queue.scan_region_connection', 'sqs-audit-region');
        foreach ($regions as $region) {
            $regionJob = ProcessRegionScanJob::dispatch($this->scan, $region, $scanType)
                ->onConnection($regionConnection);
            if ($regionConnection === 'database') {
                $regionJob->onQueue('teemops_audit_region');
            }
        }

        Log::info("{$scanType} scan dispatched to regions", [
            'scan_id' => $this->scan->id,
            'service' => $scanType,
            'regions_count' => count($regions),
        ]);
    }

    private function runGlobalScanForService(RulesEngine $rulesEngine, string $scanType, array $credentials): void
    {
        $hasDataForService = $this->scan->details()->where('service', $scanType)->exists();
        if ($hasDataForService) {
            Log::info('Skipping data collection for service (already have scan_details)', [
                'scan_id' => $this->scan->id,
                'service' => $scanType,
            ]);
            return;
        }

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
        }
    }

    private function evaluateFindings(): void
    {
        $conditionEvaluator = new ConditionEvaluator();
        $findingsEngine = new FindingsEngine($conditionEvaluator);

        try {
            $findingsEngine->evaluateScan($this->scan, [self::RULESET_BASIC]);
            Log::info('Findings evaluation completed', ['scan_id' => $this->scan->id]);
        } catch (\Throwable $e) {
            Log::error('Findings evaluation failed', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function markScanCompleted(?\App\Models\AwsAccount $awsAccount): void
    {
        try {
            $this->scan->refresh();
            if ($this->scan->status !== 'running') {
                Log::warning('Scan status is not running, skipping completion update', [
                    'scan_id' => $this->scan->id,
                    'current_status' => $this->scan->status,
                ]);
                return;
            }

            $updateResult = $this->scan->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            if (!$updateResult) {
                Log::error('Scan update returned false', ['scan_id' => $this->scan->id]);
            }

            $this->updateAwsAccountLastScanAt($awsAccount);

            $this->scan->refresh();
            Log::info('Scan marked as completed successfully', [
                'scan_id' => $this->scan->id,
                'status' => $this->scan->status,
                'completed_at' => $this->scan->completed_at,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to mark scan as completed', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function updateAwsAccountLastScanAt(?\App\Models\AwsAccount $awsAccount): void
    {
        if (!$awsAccount) {
            return;
        }
        try {
            $awsAccount->refresh();
            $awsAccount->update(['last_scan_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Failed to update AWS account last_scan_at', [
                'scan_id' => $this->scan->id,
                'aws_account_id' => $awsAccount->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleScanFailure(\Throwable $e, ?\App\Models\AwsAccount $awsAccount): void
    {
        Log::error('Scan failed', [
            'scan_id' => $this->scan->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->scan->refresh();
        $hasScanDetails = $this->scan->details()->exists();
        $hasRegionBasedScanInCatch = !empty(array_filter(
            $this->scan->scan_types ?? [],
            fn ($t) => ScanTypesService::isRegionBased($t)
        ));

        if ($hasScanDetails && !$hasRegionBasedScanInCatch) {
            try {
                $this->evaluateFindings();
                Log::info('Findings evaluation ran in catch block', ['scan_id' => $this->scan->id]);
            } catch (\Throwable $findingsEx) {
                Log::warning('Findings evaluation in catch block failed', [
                    'scan_id' => $this->scan->id,
                    'error' => $findingsEx->getMessage(),
                ]);
            }
        }

        if ($hasScanDetails && !$hasRegionBasedScanInCatch && str_contains($e->getMessage(), 'Findings evaluation')) {
            Log::warning('Marking scan as completed despite findings evaluation error', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
            ]);
            $this->scan->update([
                'status' => 'completed',
                'completed_at' => now(),
                'error_message' => 'Findings evaluation failed: ' . $e->getMessage(),
            ]);
            $this->updateAwsAccountLastScanAt($this->scan->awsAccount);
        } else {
            $this->scan->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
