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
        'organization_id',
        'aws_account_id',
        'identity_hash',
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
        'resolution_reason',
        'first_seen_at',
        'last_seen_at',
        'last_seen_scan_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Why a finding stopped being open.
     *
     * FIXED is written when a scan examined the resource and the rule no longer failed.
     * MANUAL is a person's decision. RESOURCE_GONE is specified but deliberately not
     * written yet — inferring "deleted" from an absent resource is only sound once
     * per-(service, region) collection accounting can be trusted, and today a
     * half-collected region reports as complete. See docs/features/durable-findings.md.
     */
    public const REASON_FIXED = 'fixed';
    public const REASON_MANUAL = 'manual';

    /**
     * Severity ordering used when listing findings, most severe first.
     */
    public const SEVERITY_ORDER = ['critical', 'high', 'medium', 'low'];

    /**
     * How much each severity counts when findings are weighed against each other.
     *
     * Used by the "fix these first" ranking, so eighteen mediums do not outrank eighteen
     * highs. Lives here rather than in the caller so that anything else needing to rank
     * findings picks up the same answer about which of two problems is worse — the
     * security score removed in D-12 had its own copy, and that is how numbers drift.
     */
    public const SEVERITY_WEIGHTS = [
        'critical' => 10,
        'high' => 5,
        'medium' => 2,
        'low' => 1,
    ];

    /**
     * The scan that first raised this finding.
     *
     * Note this is no longer "the scan this finding belongs to" — a finding outlives the
     * scan that spotted it. Use last_seen_scan_id for the most recent observation.
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /**
     * A finding is one problem, on one resource, under one rule, in one account.
     *
     * Hashed because the tuple cannot be indexed directly: four varchar(255) columns
     * exceed InnoDB's 3072-byte key limit under utf8mb4, and index prefix lengths do not
     * carry across to the SQLite the test suite runs on. The separator is a character
     * that cannot appear in an AWS resource id or a rule name, so two different tuples
     * cannot collide by concatenating to the same string.
     */
    public static function identityHash(
        ?string $organizationId,
        ?string $awsAccountId,
        ?string $service,
        ?string $resourceType,
        ?string $resourceId,
        ?string $findingType,
    ): string {
        return hash('sha256', implode("\x1f", [
            $organizationId ?? '',
            $awsAccountId ?? '',
            $service ?? '',
            $resourceType ?? '',
            $resourceId ?? '',
            $findingType ?? '',
        ]));
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
