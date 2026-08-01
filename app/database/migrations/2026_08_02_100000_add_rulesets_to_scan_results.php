<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-4: record which compliance benchmark raised a finding.
 *
 * A list rather than a single value, because the rule format lets one rule belong to
 * several rulesets and a finding that is both a Basic and a CIS gap should say so. The
 * alternative — folding the ruleset into a finding's identity — was rejected: it would
 * create two findings for one problem on one resource, which is the duplication D-1
 * removed.
 *
 * Nullable, and deliberately without a data migration. Findings raised before this ships
 * stay null and render as no benchmark. Backfilling would mean deriving from the rule
 * name, which is wrong the moment a rule belongs to two rulesets, and a finding labelled
 * with a benchmark that did not raise it is worse than one labelled nothing — especially
 * for the user who came here because of an audit.
 *
 * No index: there are two benchmarks with rules, so a filter on this is not selective
 * enough to earn one until profiling says otherwise.
 *
 * See docs/features/filter-findings-by-benchmark.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->json('rulesets')->nullable()->after('finding_type');
        });
    }

    public function down(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->dropColumn('rulesets');
        });
    }
};
