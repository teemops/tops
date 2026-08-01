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
use Illuminate\Bus\Batch;
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

            // Re-running this job for the same scan (SQS is at-least-once, and this job
            // retries) creates a fresh batch that supersedes the old one, so the previous
            // batch id must not linger. The old expected/processed counters are no longer
            // read by anything — their columns remain only until a cleanup migration.
            $this->scan->update(['batch_id' => null]);

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

            // Every region job across every service, planned before any is dispatched.
            // The batch is then created in one call, so its size is fixed atomically —
            // there is no window during which a completing job can compare itself against
            // a total still being accumulated. That window was #65.
            $regionJobs = [];

            foreach ($scanTypes as $scanType) {
                $serviceStartedAt = microtime(true);

                if (ScanTypesService::isRegionBased($scanType)) {
                    $dispatchedRegionServices[] = $scanType;
                    $regionJobs = array_merge(
                        $regionJobs,
                        $this->planRegionScansForService($scanner, $credentials, $scanType)
                    );
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
                'region_jobs_planned' => count($regionJobs),
                'scan_types' => $scanTypes,
                'assume_role_ms' => $assumeRoleMs,
                'global_scan_ms' => $globalScanMs,
                'dispatch_ms' => $dispatchMs,
                'orchestration_ms' => $this->elapsedMs($jobStartedAt),
            ]);

            if (empty($regionJobs)) {
                // Either no region-based service was selected, or every region was pruned
                // and none produced a job. Nothing will call back, so settle it here
                // rather than leaving it for the stale sweep an hour later.
                $this->evaluateFindings();

                if ($hasRegionBasedScan) {
                    Log::warning('No region jobs to run, completing scan now', [
                        'scan_id' => $this->scan->id,
                        'region_based_services' => $dispatchedRegionServices,
                    ]);
                }

                $this->markScanCompleted($awsAccount);
            } else {
                $this->dispatchRegionBatch($regionJobs);
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
     * Build — but do not dispatch — one ProcessRegionScanJob per region for this service.
     *
     * Planning is separated from dispatch so every service's jobs can go into a single
     * batch. The old code dispatched per service and accumulated an expected total as it
     * went, which meant that between the first dispatch and the last increment the scan
     * held a total smaller than the work outstanding. Any region job finishing in that
     * window saw "processed >= expected" and completed the whole scan, not partial — a
     * clean result for regions nobody had scanned. A single worker hid it; a second
     * worker would have fired it on the first scan. See #65.
     *
     * @return array<int, ProcessRegionScanJob>
     */
    private function planRegionScansForService(AwsSecurityScanner $scanner, array $credentials, string $scanType): array
    {
        $regions = $scanner->getAvailableRegions($credentials);

        $jobs = [];
        $prunedRegions = [];

        foreach ($regions as $region) {
            // Skipping is only ever done on positive evidence that the region holds
            // resources for other services but none for this one — never on an empty or
            // failed index, which would silently report the region as clean.
            if ($this->canSkipRegion($scanType, $region, $credentials)) {
                $prunedRegions[] = $region;
                continue;
            }

            $jobs[] = new ProcessRegionScanJob($this->scan, $region, $scanType);
        }

        Log::info("{$scanType} region scans planned", [
            'scan_id' => $this->scan->id,
            'service' => $scanType,
            'regions_count' => count($regions),
            'planned' => count($jobs),
            'pruned_regions' => $prunedRegions,
        ]);

        return $jobs;
    }

    /**
     * Dispatch every planned region job as one batch.
     *
     * allowFailures() is not optional. A batch cancels itself on the first failed job by
     * default, and a failed region has never failed a scan here — it is recorded and
     * surfaces as is_partial. Without it, one unreachable region would cancel the other
     * ~152 and the scan would report on a fraction of the account.
     *
     * The finally() callback is serialized to storage, so it cannot capture $this or any
     * model. It closes over the scan id and re-reads.
     *
     * @param  array<int, ProcessRegionScanJob>  $jobs
     */
    private function dispatchRegionBatch(array $jobs): void
    {
        $scanId = $this->scan->id;
        $connection = config('queue.scan_region_connection', 'sqs-audit-region');

        $pending = Bus::batch($jobs)
            ->name("scan:{$scanId}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($scanId) {
                $scan = Scan::find($scanId);

                if (!$scan) {
                    return;
                }

                $failed = $batch->failedJobs;

                $scan->settleRegionScan(
                    $failed > 0,
                    $failed > 0
                        ? "{$failed} of {$batch->totalJobs} region jobs failed; results are partial."
                        : null
                );
            })
            ->onConnection($connection);

        if ($connection === 'database') {
            $pending->onQueue('teemops_audit_region');
        }

        $batch = $pending->dispatch();

        $this->scan->update(['batch_id' => $batch->id]);

        Log::info('Region scan batch dispatched', [
            'scan_id' => $scanId,
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
            'connection' => $connection,
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
