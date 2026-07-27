<?php

namespace App\Models;

use App\Services\ScanTypesService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'aws_account_id',
        'scan_types',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'expected_regions_count',
    ];

    protected $casts = [
        'scan_types' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the organization that owns the scan
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the AWS account for this scan
     */
    public function awsAccount(): BelongsTo
    {
        return $this->belongsTo(AwsAccount::class);
    }

    /**
     * Get the scan results
     */
    public function results(): HasMany
    {
        return $this->hasMany(ScanResult::class);
    }

    /**
     * Get the scan details (raw API responses)
     */
    public function details(): HasMany
    {
        return $this->hasMany(ScanDetail::class);
    }

    /**
     * Validate scan types
     */
    public function hasScanType(string $type): bool
    {
        return in_array($type, $this->scan_types ?? []);
    }

    /**
     * Check if all region-based scans (EC2, RDS) are complete and mark scan as completed if so
     */
    public function checkAndMarkRegionBasedScanComplete(): void
    {
        // Get region-based services in this scan
        $regionBasedServices = ScanTypesService::getRegionBased();
        $scanRegionServices = array_filter($this->scan_types ?? [], fn($type) => ScanTypesService::isRegionBased($type));
        
        // Only check if scan includes region-based services and hasn't already
        // reached a terminal state. 'pending' must be eligible too: a scan whose
        // orchestrator job died before flipping it to 'running' still has region
        // jobs reporting in, and if we ignored it here nothing would ever
        // complete it (the stale-scan sweep uses this same method).
        if (empty($scanRegionServices) || !in_array($this->status, ['pending', 'running'], true)) {
            return;
        }

        // Count distinct (region, service) pairs - one per region job that completed
        $processedRegionJobs = $this->details()
            ->whereIn('service', $regionBasedServices)
            ->whereNotNull('region')
            ->select('region', 'service')
            ->distinct()
            ->get()
            ->count();

        $expectedRegionJobs = $this->expected_regions_count ?? null;

        $shouldComplete = false;
        $staleMinutes = 60; // Mark running scan as completed after this many minutes (partial/timeout)

        if ($expectedRegionJobs !== null) {
            $shouldComplete = $processedRegionJobs >= $expectedRegionJobs;
        }

        // Timeout: if scan has been running too long, mark completed (partial) so it doesn't stay "Running" forever
        if (!$shouldComplete && $this->started_at && $this->started_at->diffInMinutes(now()) >= $staleMinutes) {
            \Illuminate\Support\Facades\Log::warning('Region-based scan timed out, marking completed with partial results', [
                'scan_id' => $this->id,
                'processed_region_jobs' => $processedRegionJobs,
                'expected_region_jobs' => $expectedRegionJobs,
            ]);
            $shouldComplete = true;
        }

        if (!$shouldComplete && $expectedRegionJobs === null && $this->started_at) {
            // Fallback: no expected count stored (legacy), use 10 min + at least 5 region jobs
            $shouldComplete = $this->started_at->diffInMinutes(now()) > 10 && $processedRegionJobs >= 5;
        }

        if ($shouldComplete) {
            $timedOut = $expectedRegionJobs !== null
                && $processedRegionJobs < $expectedRegionJobs
                && $this->started_at
                && $this->started_at->diffInMinutes(now()) >= $staleMinutes;

            // Evaluate findings for non-region-based scan types present in this scan
            $nonRegionTypes = array_values(
                array_intersect($this->scan_types ?? [], ScanTypesService::getNonRegionBased())
            );
            
            if (!empty($nonRegionTypes)) {
                try {
                    $conditionEvaluator = new \App\Services\RulesEngine\ConditionEvaluator();
                    $findingsEngine = new \App\Services\RulesEngine\FindingsEngine($conditionEvaluator);
                    $findingsEngine->evaluateScan($this, ['basic']);
                    
                    \Illuminate\Support\Facades\Log::info('Findings evaluation completed for non-region-based types', [
                        'scan_id' => $this->id,
                        'types' => $nonRegionTypes,
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Findings evaluation failed for non-region-based types', [
                        'scan_id' => $this->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
                'error_message' => $timedOut
                    ? "Scan timed out (partial results). {$processedRegionJobs}/{$expectedRegionJobs} region jobs completed."
                    : null,
            ]);

            if ($this->awsAccount) {
                $this->awsAccount->update(['last_scan_at' => now()]);
            }

            \Illuminate\Support\Facades\Log::info('Region-based scan marked as completed', [
                'scan_id' => $this->id,
                'processed_region_jobs' => $processedRegionJobs,
                'expected_region_jobs' => $expectedRegionJobs,
                'timed_out' => $timedOut,
            ]);
        } else {
            // Without this, a scan that never reaches its expected count is silently
            // stuck with no indication of why. If processed is plateauing below
            // expected, expected_regions_count is likely inflated (e.g. the
            // orchestrator job ran more than once).
            \Illuminate\Support\Facades\Log::debug('Region-based scan not complete yet', [
                'scan_id' => $this->id,
                'status' => $this->status,
                'processed_region_jobs' => $processedRegionJobs,
                'expected_region_jobs' => $expectedRegionJobs,
                'started_at' => $this->started_at,
            ]);
        }
    }
}
