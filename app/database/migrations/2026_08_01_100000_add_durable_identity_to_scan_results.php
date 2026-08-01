<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema half of D-1 (durable findings). Adds the columns that let a finding be one
 * lasting record of one problem on one resource, instead of a row per scan.
 *
 * Nullable throughout, and no unique constraint yet: the backfill runs in its own data
 * migration, and the constraint follows once existing rows have been collapsed. Splitting
 * it that way keeps this file reversible without touching a byte of data.
 *
 * See docs/features/durable-findings.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            // A finding belongs to the account, not the scan that happened to spot it.
            // Both are reachable today only by joining scans, which is why every read
            // path has had to carry that join.
            $table->uuid('organization_id')->nullable()->after('id');
            $table->uuid('aws_account_id')->nullable()->after('organization_id');

            // Identity, as a hash. MySQL cannot index the six-column tuple directly —
            // four varchar(255) columns alone exceed InnoDB's 3072-byte key limit under
            // utf8mb4 — and prefix lengths are not portable to the SQLite used in tests.
            $table->char('identity_hash', 64)->nullable()->after('finding_type');

            // scan_id stays as "the scan that first raised this finding". The lifecycle
            // is carried by these.
            $table->timestamp('first_seen_at')->nullable()->after('status');
            $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');
            $table->uuid('last_seen_scan_id')->nullable()->after('last_seen_at');

            // Why a finding was resolved. "No longer failing" and "the resource is gone"
            // are different news and must not flatten into one word. Only 'fixed' and
            // 'manual' are written today — 'resource_gone' is deferred until per-region
            // collection accounting can be trusted (see the story).
            $table->string('resolution_reason')->nullable()->after('resolved_at');

            $table->index('organization_id');
            $table->index('aws_account_id');
            $table->index('last_seen_scan_id');
            $table->index(['organization_id', 'status'], 'scan_results_org_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->dropIndex('scan_results_org_status_index');
            $table->dropIndex(['last_seen_scan_id']);
            $table->dropIndex(['aws_account_id']);
            $table->dropIndex(['organization_id']);

            $table->dropColumn([
                'organization_id',
                'aws_account_id',
                'identity_hash',
                'first_seen_at',
                'last_seen_at',
                'last_seen_scan_id',
                'resolution_reason',
            ]);
        });
    }
};
