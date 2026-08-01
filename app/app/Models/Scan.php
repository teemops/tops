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
        'rulesets',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'expected_regions_count',
        'batch_id',
        'processed_regions_count',
        'is_partial',
    ];

    protected $casts = [
        'scan_types' => 'array',
        'rulesets' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_partial' => 'boolean',
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
     * Settle a region-based scan, exactly once.
     *
     * The write is a guarded UPDATE rather than a read-then-write: whichever of the batch
     * callback and the stale-scan sweep gets here first claims the scan, and the other is
     * told it lost and does nothing. Without the guard both could complete the same scan
     * and evaluate its findings twice (#66).
     *
     * $partial is what consumers act on. A scan that missed regions must never read as a
     * clean bill of health — that is the whole point of the flag, and the reason the
     * previous counter-based completion was a security bug rather than an accounting one.
     *
     * @return bool Whether this caller was the one that completed the scan.
     */
    public function settleRegionScan(bool $partial, ?string $reason = null): bool
    {
        $claimed = static::query()
            ->whereKey($this->id)
            ->whereIn('status', ['pending', 'running'])
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'is_partial' => $partial,
                'error_message' => $reason,
            ]);

        if ($claimed === 0) {
            \Illuminate\Support\Facades\Log::info('Scan already settled by another writer', [
                'scan_id' => $this->id,
            ]);

            return false;
        }

        $this->refresh();

        // Region jobs evaluate their own service's findings as they go; anything not
        // region-based was collected inline by the orchestrator and still needs a pass.
        $nonRegionTypes = array_values(
            array_intersect($this->scan_types ?? [], ScanTypesService::getNonRegionBased())
        );

        if (!empty($nonRegionTypes)) {
            try {
                $findingsEngine = new \App\Services\RulesEngine\FindingsEngine(
                    new \App\Services\RulesEngine\ConditionEvaluator()
                );
                $findingsEngine->evaluateScan($this, $this->rulesets ?? ['basic']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Findings evaluation failed while settling scan', [
                    'scan_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->awsAccount?->update(['last_scan_at' => now()]);

        \Illuminate\Support\Facades\Log::info('Region-based scan settled', [
            'scan_id' => $this->id,
            'is_partial' => $partial,
            'reason' => $reason,
            'total_ms' => $this->started_at
                ? (int) $this->started_at->diffInMilliseconds(now())
                : null,
        ]);

        return true;
    }
}
