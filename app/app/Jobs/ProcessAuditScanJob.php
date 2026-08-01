<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\AwsSecurityScanner;
use App\Services\RegionResourceIndex;
use App\Services\ScanTypesService;
use App\Services\ServiceRegistry;
use App\Services\RulesEngine\RulesEngine;
use App\Services\RulesEngine\FindingsEngine;
use App\Services\RulesEngine\ConditionEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessAuditScanJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    private const RULESET_BASIC = 'basic';

    /**
     * Tagging API index per region, memoised for this job run so a scan covering many
     * services costs one call per region rather than one per (service, region).
     * A null entry means the region could not be indexed and must not be pruned.
     *
     * @var array<string, string[]|null>
     */
    private array $regionServicePrefixes = [];

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
        $jobStartedAt = microtime(true);

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

            // Region dispatch must be idempotent. expected_regions_count is compared
            // against the number of DISTINCT (region, service) pairs recorded in
            // scan_details, which is capped at regions x region-based-scan-types. If
            // this job runs more than once for the same scan (SQS at-least-once
            // redelivery, a retry, or a stale queued job being replayed) and we simply
            // accumulated, the expected total would exceed what can ever be recorded
            // and the scan could never complete. Reset per run so re-runs converge.
            $this->scan->update(['expected_regions_count' => 0]);

            $scanner = new AwsSecurityScanner($roleArn, $externalId);

            $assumeRoleStartedAt = microtime(true);
            $credentials = $scanner->assumeRole();
            $assumeRoleMs = $this->elapsedMs($assumeRoleStartedAt);

            $rulesEngine = new RulesEngine();

            // Track which region-based services were dispatched (for log context only).
            // The completion decision below is driven by whether ANY selected scan type
            // is region-based — not by a per-service allowlist — so newly added
            // region-based services (CloudTrail, Lambda, KMS, ...) correctly wait for
            // their region jobs instead of completing prematurely with no results.
            $dispatchedRegionServices = [];

            // Per-service timings for the parallel-scan baseline (PERF-1, #64).
            // global_scan_ms against dispatch_ms is the measurement that decides
            // whether moving iam/s3 out of this job (#71) is worth doing: every
            // region dispatch queues behind the global services running inline here.
            $globalScanMs = [];
            $dispatchMs = [];

            foreach ($scanTypes as $scanType) {
                $serviceStartedAt = microtime(true);

                if (ScanTypesService::isRegionBased($scanType)) {
                    $dispatchedRegionServices[] = $scanType;
                    $this->dispatchRegionScansForService($scanner, $credentials, $scanType);
                    $dispatchMs[$scanType] = $this->elapsedMs($serviceStartedAt);
                } else {
                    $this->runGlobalScanForService($rulesEngine, $scanType, $credentials);
                    $globalScanMs[$scanType] = $this->elapsedMs($serviceStartedAt);
                }
            }

            $hasRegionBasedScan = !empty($dispatchedRegionServices);

            Log::info('All scan types processed, starting findings evaluation', [
                'scan_id' => $this->scan->id,
                'region_based_services' => $dispatchedRegionServices,
                'has_region_based' => $hasRegionBasedScan,
                'scan_types' => $scanTypes,
                'assume_role_ms' => $assumeRoleMs,
                'global_scan_ms' => $globalScanMs,
                'dispatch_ms' => $dispatchMs,
                'orchestration_ms' => $this->elapsedMs($jobStartedAt),
            ]);

            if (!$hasRegionBasedScan) {
                $this->evaluateFindings();
                $this->markScanCompleted($awsAccount);
            } else {
                Log::info('Scan with region-based service - waiting for region scans to complete', [
                    'scan_id' => $this->scan->id,
                    'region_based_services' => $dispatchedRegionServices,
                ]);

                // If every region job failed to dispatch (or getAvailableRegions
                // returned none), nothing will ever call back to complete this scan.
                // Check now instead of waiting on the periodic stale-scan sweep.
                $this->scan->refresh();
                if (($this->scan->expected_regions_count ?? 0) === 0) {
                    Log::warning('No region jobs were successfully dispatched, completing scan now', [
                        'scan_id' => $this->scan->id,
                    ]);
                    $this->scan->checkAndMarkRegionBasedScanComplete();
                }
            }

            Log::info('Scan processing completed', [
                'scan_id' => $this->scan->id,
                'region_based_services' => $dispatchedRegionServices,
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

    /**
     * Milliseconds since a microtime(true) mark, for the phase timings above.
     */
    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
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

    /**
     * Dispatch one ProcessRegionScanJob per available region for this scan type.
     *
     * expected_regions_count only counts jobs we actually confirmed were pushed to
     * the queue — not the full region list up front. If any single region's push
     * fails, it's logged and excluded from the expected total, so the scan can
     * still reach completion instead of waiting forever for a job that was never
     * delivered. Each region is dispatched explicitly (Bus::dispatch on a fully
     * configured job instance) rather than via the PendingDispatch fluent/deferred
     * dispatch, so a failure is caught right here, per region, instead of
     * potentially aborting the rest of the loop silently.
     */
    private function dispatchRegionScansForService(AwsSecurityScanner $scanner, array $credentials, string $scanType): void
    {
        $regions = $scanner->getAvailableRegions($credentials);
        $regionConnection = config('queue.scan_region_connection', 'sqs-audit-region');

        $dispatched = 0;
        $failedRegions = [];
        $prunedRegions = [];

        foreach ($regions as $region) {
            // Skipping is only ever done on positive evidence that the region holds
            // resources for other services but none for this one — never on an empty or
            // failed index, which would silently report the region as clean.
            if ($this->canSkipRegion($scanType, $region, $credentials)) {
                $prunedRegions[] = $region;
                continue;
            }

            try {
                $job = new ProcessRegionScanJob($this->scan, $region, $scanType);
                $job->onConnection($regionConnection);
                if ($regionConnection === 'database') {
                    $job->onQueue('teemops_audit_region');
                }

                Bus::dispatch($job);
                $dispatched++;
            } catch (\Throwable $e) {
                $failedRegions[] = $region;
                Log::error("Failed to dispatch region scan job", [
                    'scan_id' => $this->scan->id,
                    'service' => $scanType,
                    'region' => $region,
                    'connection' => $regionConnection,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $currentExpected = $this->scan->expected_regions_count ?? 0;
        $this->scan->update([
            'expected_regions_count' => $currentExpected + $dispatched,
        ]);

        Log::info("{$scanType} scan dispatched to regions", [
            'scan_id' => $this->scan->id,
            'service' => $scanType,
            'regions_count' => count($regions),
            'dispatched' => $dispatched,
            'failed_regions' => $failedRegions,
            'pruned_regions' => $prunedRegions,
        ]);
    }

    /**
     * Whether this service demonstrably has nothing in this region.
     *
     * Returns false unless the Tagging API positively indexed the region and this
     * service was absent from it. Every uncertain case — feature disabled, index
     * unavailable, region reported nothing at all — dispatches the job, because the cost
     * of a wasted job is a few seconds and the cost of a wrong skip is a region reported
     * compliant that nobody looked at.
     *
     * Regions are indexed once per job run, not once per service, so a scan covering
     * twenty services still makes one Tagging API call per region.
     */
    private function canSkipRegion(string $scanType, string $region, array $credentials): bool
    {
        if (!config('scan.prune_regions_with_tagging', false)) {
            return false;
        }

        if (!array_key_exists($region, $this->regionServicePrefixes)) {
            $awsAccount = $this->scan->awsAccount;

            $this->regionServicePrefixes[$region] = (new RegionResourceIndex())->servicePrefixesIn(
                $awsAccount->iam_role_arn,
                $awsAccount->external_id,
                $credentials,
                $region
            );
        }

        $prefixes = $this->regionServicePrefixes[$region];

        if ($prefixes === null) {
            return false;
        }

        $arnService = ServiceRegistry::get($scanType)['arnService'] ?? $scanType;

        return !in_array($arnService, $prefixes, true);
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

        $startedAt = microtime(true);

        try {
            $findingsEngine->evaluateScan($this->scan, $this->scan->rulesets ?? [self::RULESET_BASIC]);
            Log::info('Findings evaluation completed', [
                'scan_id' => $this->scan->id,
                'findings_ms' => $this->elapsedMs($startedAt),
            ]);
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
            if (!in_array($this->scan->status, ['pending', 'running'], true)) {
                Log::warning('Scan already in a terminal state, skipping completion update', [
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
                // Wall time for the whole scan. This is the global-services-only path;
                // region-based scans report their total from
                // Scan::checkAndMarkRegionBasedScanComplete() instead.
                'total_ms' => $this->scan->started_at
                    ? (int) $this->scan->started_at->diffInMilliseconds(now())
                    : null,
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
