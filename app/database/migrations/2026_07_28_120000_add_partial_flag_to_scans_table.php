<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A region-based scan that times out is marked "completed" so it does not sit as
 * "Running" forever, but it has only part of the account's data. Until now the only
 * record of that was prose in error_message, which nothing reads — so a scan that
 * reached 12 of 34 regions produced a compliance score presented exactly like a full
 * one, and a better one, since the regions never scanned contributed no findings.
 *
 * These columns make partial completion a fact the app can act on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->boolean('is_partial')->default(false)->after('status')
                ->comment('Completed without collecting every dispatched region job');
            $table->unsignedInteger('processed_regions_count')->nullable()->after('expected_regions_count')
                ->comment('Region jobs that reported data, recorded at completion');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn(['is_partial', 'processed_regions_count']);
        });
    }
};
