<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a per-scan list of rulesets to evaluate (e.g. ["basic"], ["cis","pci"]).
     * Previously the ruleset was hardcoded to "basic" in the jobs; storing it here
     * lets a scan record which compliance profile(s) it was run against. Nullable so
     * existing scans (and any created without a profile) fall back to "basic".
     */
    public function up(): void
    {
        if (!Schema::hasColumn('scans', 'rulesets')) {
            Schema::table('scans', function (Blueprint $table) {
                $table->json('rulesets')->nullable()->after('scan_types');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('scans', 'rulesets')) {
            Schema::table('scans', function (Blueprint $table) {
                $table->dropColumn('rulesets');
            });
        }
    }
};
