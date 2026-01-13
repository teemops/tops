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
        
        // Only check if scan includes region-based services and is still running
        if (empty($scanRegionServices) || $this->status !== 'running') {
            return;
        }

        // Get all unique regions that have scan_details for any region-based service
        $processedRegions = $this->details()
            ->whereIn('service', $regionBasedServices)
            ->distinct()
            ->pluck('region')
            ->filter()
            ->unique()
            ->count();

        // Get expected regions count (stored when regions were dispatched)
        // If not stored, we'll use a fallback: check if scan has been running for a while
        $expectedRegions = $this->expected_regions_count ?? null;

        $shouldComplete = false;

        if ($expectedRegions !== null) {
            // We have expected count, check if all regions are done
            $shouldComplete = $processedRegions >= $expectedRegions;
        } else {
            // Fallback: If scan has been running for more than 10 minutes and has scan_details,
            // and we've processed at least 5 regions (reasonable minimum), mark as complete
            // This handles cases where expected_regions_count wasn't stored
            $shouldComplete = $this->started_at 
                && $this->started_at->diffInMinutes(now()) > 10 
                && $processedRegions >= 5;
        }

        if ($shouldComplete) {
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
                    // Don't fail the scan if findings evaluation fails
                }
            }

            // Mark scan as completed
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update AWS account last scan time
            if ($this->awsAccount) {
                $this->awsAccount->update([
                    'last_scan_at' => now(),
                ]);
            }

            \Illuminate\Support\Facades\Log::info('Region-based scan marked as completed', [
                'scan_id' => $this->id,
                'processed_regions' => $processedRegions,
                'expected_regions' => $expectedRegions,
            ]);
        }
    }
}
