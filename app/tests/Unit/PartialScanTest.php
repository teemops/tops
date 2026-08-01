<?php

namespace Tests\Unit;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanDetail;
use App\Models\ScanResult;
use App\Services\ComplianceScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A region-based scan that times out is still marked "completed" so it does not sit as
 * "Running" forever — but it holds only part of the account. These tests pin that the
 * shortfall is recorded structurally and kept out of compliance scoring, because a scan
 * that never reached half its regions found no problems there and would otherwise score
 * as if those regions were clean.
 */
class PartialScanTest extends TestCase
{
    use RefreshDatabase;

    private function runningRegionScan(int $minutesAgo = 5): Scan
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->create(['organization_id' => $organization->id]);

        return Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'status' => 'running',
            'scan_types' => ['ec2'],
            'rulesets' => ['basic'],
            'started_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_a_scan_settled_with_failures_is_completed_and_flagged_partial(): void
    {
        $scan = $this->runningRegionScan(minutesAgo: 90);

        $scan->settleRegionScan(true, '7 of 10 region jobs failed; results are partial.');
        $scan->refresh();

        $this->assertSame('completed', $scan->status);
        $this->assertTrue($scan->is_partial);
        $this->assertStringContainsString('partial', $scan->error_message);
    }

    public function test_a_scan_whose_regions_all_succeeded_is_not_flagged_partial(): void
    {
        $scan = $this->runningRegionScan();

        $scan->settleRegionScan(false);
        $scan->refresh();

        $this->assertSame('completed', $scan->status);
        $this->assertFalse($scan->is_partial);
        $this->assertNull($scan->error_message);
    }

    /**
     * The guard that makes the batch callback and the stale sweep safe to both exist:
     * exactly one of them settles a given scan (#66).
     */
    public function test_only_the_first_caller_settles_a_scan(): void
    {
        $scan = $this->runningRegionScan();

        $this->assertTrue($scan->settleRegionScan(false));

        // A late sweep must not overwrite a clean result with a partial one.
        $this->assertFalse($scan->settleRegionScan(true, 'stale sweep'));

        $scan->refresh();
        $this->assertFalse($scan->is_partial);
        $this->assertNull($scan->error_message);
    }

    public function test_settling_records_the_scan_against_its_aws_account(): void
    {
        $scan = $this->runningRegionScan();

        $scan->settleRegionScan(false);

        $this->assertNotNull($scan->awsAccount->fresh()->last_scan_at);
    }

    /**
     * The regression this exists to prevent: a partial scan scoring as compliant because
     * the regions it never reached could not contribute findings.
     */
    public function test_compliance_scoring_ignores_partial_scans(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->create(['organization_id' => $organization->id]);

        $partial = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'status' => 'completed',
            'is_partial' => true,
            'rulesets' => ['basic'],
        ]);

        ScanResult::create([
            'scan_id' => $partial->id,
            'severity' => 'high',
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'alice',
            'finding_type' => 'tops-iam-001',
            'title' => 'IAM User MFA Token - alice',
            'description' => 'x',
            'status' => 'open',
        ]);

        $scores = (new ComplianceScoreService())->scoresFor($organization->id, now()->subDays(30));

        $basic = collect($scores)->firstWhere('key', 'basic');

        $this->assertNotNull($basic);
        $this->assertFalse(
            $basic['evaluated'],
            'A partial scan must not count as having evaluated the framework'
        );
        $this->assertNull($basic['score']);
    }

    public function test_compliance_scoring_still_uses_complete_scans(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->create(['organization_id' => $organization->id]);

        Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'status' => 'completed',
            'is_partial' => false,
            'rulesets' => ['basic'],
        ]);

        $scores = (new ComplianceScoreService())->scoresFor($organization->id, now()->subDays(30));
        $basic = collect($scores)->firstWhere('key', 'basic');

        $this->assertTrue($basic['evaluated']);
        $this->assertSame(100, $basic['score']);
    }
}
