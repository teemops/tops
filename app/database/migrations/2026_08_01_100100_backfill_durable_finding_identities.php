<?php

use App\Models\ScanResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data half of D-1. Three steps, and the order is the point:
 *
 *  1. Give every existing finding its account, organization and lifecycle timestamps.
 *  2. Collapse the duplicates the old row-per-scan model created — grouping on the
 *     identity *columns*, which are now populated — down to one row per problem.
 *  3. Only then write identity_hash, to rows that are unique by construction.
 *
 * Writing the hash last is deliberate. Doing it first means every duplicate group briefly
 * holds the same hash, which is fine on a live upgrade (the unique index is added by the
 * next migration) but makes this file quietly dependent on running exactly once, in
 * exactly that order. Written this way it is idempotent and safe to re-run against a
 * database that already has the constraint.
 *
 * Deliberately separate from the schema migration, per docs/practices/database.md.
 */
return new class extends Migration
{
    /**
     * The columns a finding's identity is made of.
     */
    private const IDENTITY_COLUMNS = [
        'organization_id',
        'aws_account_id',
        'service',
        'resource_type',
        'resource_id',
        'finding_type',
    ];

    public function up(): void
    {
        $this->backfillOwnershipAndLifecycle();
        $this->collapseDuplicates();
        $this->writeIdentityHashes();
    }

    /**
     * Irreversible by nature — collapsed rows cannot be un-collapsed, and nothing depends
     * on them coming back. The schema migration's down() drops the columns, which is the
     * meaningful rollback.
     */
    public function down(): void
    {
        // No-op.
    }

    private function backfillOwnershipAndLifecycle(): void
    {
        DB::table('scan_results')
            ->select(
                'scan_results.id',
                'scan_results.scan_id',
                'scan_results.created_at',
                'scans.organization_id as scan_organization_id',
                'scans.aws_account_id as scan_aws_account_id',
            )
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->orderBy('scan_results.id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('scan_results')
                        ->where('id', $row->id)
                        ->update([
                            'organization_id' => $row->scan_organization_id,
                            'aws_account_id' => $row->scan_aws_account_id,
                            'first_seen_at' => $row->created_at,
                            'last_seen_at' => $row->created_at,
                            'last_seen_scan_id' => $row->scan_id,
                        ]);
                }
            });
    }

    private function collapseDuplicates(): void
    {
        $duplicateGroups = DB::table('scan_results')
            ->select(self::IDENTITY_COLUMNS)
            ->whereNotNull('organization_id')
            ->groupBy(self::IDENTITY_COLUMNS)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('scan_results')
                ->where((array) $group)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            if ($rows->count() < 2) {
                continue;
            }

            $keep = $rows->first();
            $latest = $rows->last();

            // The earliest row is the record — it carries the true first_seen_at, and any
            // id already handed out in a URL. The latest observation supplies the content
            // and the last_seen columns, matching the rule that the most recent scan to
            // examine a resource is authoritative.
            //
            // A human decision outranks both: if any copy was ignored or resolved, that
            // verdict survives rather than being reset by whichever row sorted last.
            $decided = $rows->firstWhere('status', 'ignored')
                ?? $rows->firstWhere('status', 'resolved');

            DB::table('scan_results')
                ->where('id', $keep->id)
                ->update([
                    'severity' => $latest->severity,
                    'title' => $latest->title,
                    'description' => $latest->description,
                    'remediation' => $latest->remediation,
                    'status' => $decided->status ?? $keep->status,
                    'resolved_at' => $decided->resolved_at ?? null,
                    'resolution_reason' => ($decided !== null && $decided->status === 'resolved')
                        ? ScanResult::REASON_MANUAL
                        : null,
                    'last_seen_at' => $latest->created_at,
                    'last_seen_scan_id' => $latest->scan_id,
                ]);

            DB::table('scan_results')
                ->where((array) $group)
                ->where('id', '!=', $keep->id)
                ->delete();
        }
    }

    private function writeIdentityHashes(): void
    {
        DB::table('scan_results')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('scan_results')
                        ->where('id', $row->id)
                        ->update([
                            'identity_hash' => ScanResult::identityHash(
                                $row->organization_id,
                                $row->aws_account_id,
                                $row->service,
                                $row->resource_type,
                                $row->resource_id,
                                $row->finding_type,
                            ),
                        ]);
                }
            });
    }
};
