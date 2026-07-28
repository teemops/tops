<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanResult extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'scan_id',
        'severity',
        'service',
        'resource_type',
        'resource_id',
        'finding_type',
        'title',
        'description',
        'remediation',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Severity ordering used when listing findings, most severe first.
     */
    public const SEVERITY_ORDER = ['critical', 'high', 'medium', 'low'];

    /**
     * Get the scan that owns this result
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /**
     * Order findings by severity, most severe first.
     *
     * Uses a CASE expression rather than MySQL's FIELD() so the same query runs
     * on the SQLite database the test suite uses.
     *
     * @param  string  $column  Column to sort on, qualified where the query joins.
     */
    public function scopeOrderBySeverity(Builder $query, string $column = 'severity'): Builder
    {
        $cases = '';
        foreach (self::SEVERITY_ORDER as $position => $severity) {
            $cases .= " WHEN ? THEN {$position}";
        }

        return $query->orderByRaw(
            "CASE {$column}{$cases} ELSE " . count(self::SEVERITY_ORDER) . ' END',
            self::SEVERITY_ORDER
        );
    }
}
