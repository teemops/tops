<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The constraint that makes a finding durable, added once the backfill has collapsed the
 * duplicates the old row-per-scan model created.
 *
 * This is the whole guarantee: one row per (organization, account, service, resource,
 * rule), enforced by the database rather than by a read-then-write in application code.
 * It also closes a concurrency bug found in the parallel-region-scan design — two region
 * jobs evaluating the same rule at the same moment both saw an empty dedupe SELECT and
 * both inserted, because nothing stopped them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->unique('identity_hash', 'scan_results_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->dropUnique('scan_results_identity_unique');
        });
    }
};
