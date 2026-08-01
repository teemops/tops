<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-2: remember which job batch is doing a scan's region work.
 *
 * The scan's completion moves from a counter that was accumulated *while* jobs were
 * already running — the race that let one region job complete a whole scan — to a batch
 * whose size is fixed atomically at dispatch. Holding the batch id lets the stale-scan
 * sweep tell an orphaned batch from one still working, and gives cancellation something
 * real to cancel.
 *
 * Nullable: scans that ran before this, and scans with no region-based services at all,
 * never have one.
 *
 * See docs/features/parallel-region-scans.md and #65.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->string('batch_id')->nullable()->after('expected_regions_count');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }
};
