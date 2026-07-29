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

    private function scanWithRegionsOutstanding(int $expected, int $delivered, int $minutesAgo): Scan
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->create(['organization_id' => $organization->id]);

        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'status' => 'running',
            'scan_types' => ['ec2'],
            'rulesets' => ['basic'],
            'started_at' => now()->subMinutes($minutesAgo),
            'expected_regions_count' => $expected,
        ]);

        foreach (range(1, $delivered) as $i) {
            ScanDetail::create([
                'scan_id' => $scan->id,
                'service' => 'ec2',
                'api_method' => 'describeInstances',
                'raw_data' => ['Reservations' => []],
                'region' => "us-test-{$i}",
            ]);
        }

        return $scan;
    }

    public function test_a_timed_out_scan_is_completed_and_flagged_partial(): void
    {
        $scan = $this->scanWithRegionsOutstanding(expected: 10, delivered: 3, minutesAgo: 90);

        $scan->checkAndMarkRegionBasedScanComplete();
        $scan->refresh();

        $this->assertSame('completed', $scan->status);
        $this->assertTrue($scan->is_partial);
        $this->assertSame(3, $scan->processed_regions_count);
        $this->assertStringContainsString('partial results', $scan->error_message);
    }

    public function test_a_scan_that_reached_every_region_is_not_flagged_partial(): void
    {
        $scan = $this->scanWithRegionsOutstanding(expected: 3, delivered: 3, minutesAgo: 5);

        $scan->checkAndMarkRegionBasedScanComplete();
        $scan->refresh();

        $this->assertSame('completed', $scan->status);
        $this->assertFalse($scan->is_partial);
        $this->assertSame(3, $scan->processed_regions_count);
        $this->assertNull($scan->error_message);
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
