<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InsightsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_aggregated_insights_for_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->completed()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        ScanResult::factory()->create([
            'scan_id' => $scan->id,
            'severity' => 'critical',
            'service' => 's3',
            'status' => 'open',
            'finding_type' => 's3-public-bucket',
        ]);

        ScanResult::factory()->create([
            'scan_id' => $scan->id,
            'severity' => 'high',
            'service' => 'iam',
            'status' => 'resolved',
            'resolved_at' => now(),
            'finding_type' => 'iam-privilege-escalation',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()
            ->assertJsonPath('summary.totalFindings', 2)
            ->assertJsonPath('summary.openFindings', 1)
            ->assertJsonPath('summary.criticalOpen', 1)
            ->assertJsonPath('summary.remediationRate', 50)
            ->assertJsonStructure([
                'period',
                'summary',
                'severityDistribution',
                'trend',
                'topServices',
                'keyInsights',
            ]);
    }

    /**
     * Build an org owned by $user with one completed scan, ready for findings.
     *
     * @param string[]|null $rulesets
     * @return array{0: User, 1: Organization, 2: Scan}
     */
    private function orgWithScan(?array $rulesets = null, ?Carbon $scanCreatedAt = null): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan = Scan::factory()->completed()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'rulesets' => $rulesets,
            'created_at' => $scanCreatedAt ?? now(),
        ]);

        return [$user, $organization, $scan];
    }

    /**
     * Findings carry their own organization and account since D-1, which is what lets the
     * breakdown read them without going through the scan.
     */
    private function finding(Organization $organization, Scan $scan, array $attributes = []): ScanResult
    {
        return ScanResult::factory()->create(array_merge([
            'scan_id' => $scan->id,
            'organization_id' => $organization->id,
            'aws_account_id' => $scan->aws_account_id,
            'status' => 'open',
        ], $attributes));
    }

    public function test_insights_break_findings_down_by_service_with_severities(): void
    {
        [$user, $organization, $scan] = $this->orgWithScan();

        $this->finding($organization, $scan, ['service' => 'ec2', 'severity' => 'high']);
        $this->finding($organization, $scan, ['service' => 'ec2', 'severity' => 'medium']);
        $this->finding($organization, $scan, ['service' => 's3', 'severity' => 'low']);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()
            ->assertJsonPath('breakdown.byService.0.key', 'ec2')
            ->assertJsonPath('breakdown.byService.0.total', 2)
            ->assertJsonPath('breakdown.byService.0.severities.high', 1)
            ->assertJsonPath('breakdown.byService.0.severities.medium', 1)
            ->assertJsonPath('breakdown.byService.1.key', 's3');
    }

    public function test_insights_also_break_findings_down_by_finding_type(): void
    {
        [$user, $organization, $scan] = $this->orgWithScan();

        $this->finding($organization, $scan, ['finding_type' => 'cis-5.1', 'severity' => 'high']);
        $this->finding($organization, $scan, ['finding_type' => 'cis-5.1', 'severity' => 'high']);
        $this->finding($organization, $scan, ['finding_type' => 'cis-3.9', 'severity' => 'medium']);

        $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d")
            ->assertOk()
            ->assertJsonPath('breakdown.byFindingType.0.key', 'cis-5.1')
            ->assertJsonPath('breakdown.byFindingType.0.total', 2);
    }

    /**
     * The one deliberate inconsistency on this page, and the reason it is deliberate: the
     * breakdown is current state, so it has to read the same number as Scan detail and
     * Findings. Everything else here is scoped to the selected period.
     */
    public function test_the_breakdown_is_current_state_and_ignores_the_period(): void
    {
        [$user, $organization, $scan] = $this->orgWithScan();

        // Older than every selectable period.
        $this->finding($organization, $scan, [
            'service' => 'ec2',
            'created_at' => now()->subDays(400),
        ]);

        foreach (['30d', '90d', '365d'] as $period) {
            $this->actingAs($user)
                ->getJson("/api/organizations/{$organization->org_id}/insights?period={$period}")
                ->assertOk()
                ->assertJsonPath('breakdown.byService.0.key', 'ec2')
                ->assertJsonPath('breakdown.byService.0.total', 1);
        }
    }

    public function test_an_organization_with_no_findings_gets_an_empty_breakdown(): void
    {
        [$user, $organization] = $this->orgWithScan();

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()
            ->assertJsonPath('breakdown.byService', [])
            ->assertJsonPath('breakdown.summary.total', 0);
    }

    public function test_the_breakdown_excludes_other_organizations(): void
    {
        [$user, $organization, $scan] = $this->orgWithScan();
        [, $otherOrganization, $otherScan] = $this->orgWithScan();

        $this->finding($otherOrganization, $otherScan, ['service' => 'ec2']);
        $this->finding($organization, $scan, ['service' => 's3']);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()->assertJsonPath('breakdown.byService.0.key', 's3');
        $this->assertCount(1, $response->json('breakdown.byService'));
    }

    public function test_trend_uses_daily_buckets_for_thirty_days(): void
    {
        [$user, $organization] = $this->orgWithScan();

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()->assertJsonPath('granularity', 'day');
        $this->assertCount(31, $response->json('trend'));
    }

    public function test_trend_uses_weekly_buckets_for_ninety_days(): void
    {
        [$user, $organization] = $this->orgWithScan();

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=90d");

        $response->assertOk()->assertJsonPath('granularity', 'week');
        // 91 days spans at most 14 calendar weeks — never one point per day.
        $this->assertLessThanOrEqual(14, count($response->json('trend')));
        $this->assertGreaterThanOrEqual(13, count($response->json('trend')));
    }

    public function test_trend_uses_monthly_buckets_for_a_year(): void
    {
        [$user, $organization] = $this->orgWithScan();

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=365d");

        $response->assertOk()->assertJsonPath('granularity', 'month');
        // Regression guard: this used to return one point per day (366 of them).
        $this->assertLessThanOrEqual(13, count($response->json('trend')));
    }

    public function test_scores_only_frameworks_that_a_scan_actually_evaluated(): void
    {
        [$user, $organization, $scan] = $this->orgWithScan(['cis']);

        // One failing CIS check out of the 22 the ruleset declares.
        ScanResult::factory()->create([
            'scan_id' => $scan->id,
            'severity' => 'high',
            'service' => 'iam',
            'status' => 'open',
            'finding_type' => 'cis-1.4',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk();

        $frameworks = collect($response->json('compliance.frameworks'))->keyBy('key');

        $this->assertTrue($frameworks['cis']['evaluated']);
        $this->assertSame(22, $frameworks['cis']['totalRules']);
        $this->assertSame(1, $frameworks['cis']['failingRules']);
        $this->assertSame(95, $frameworks['cis']['score']);

        // 'basic' was never run, so it must not be reported as compliant.
        $this->assertFalse($frameworks['basic']['evaluated']);
        $this->assertNull($frameworks['basic']['score']);

        // The average covers evaluated frameworks only.
        $this->assertSame(95, $response->json('summary.averageCompliance'));
    }

    public function test_resolved_findings_do_not_count_against_a_compliance_score(): void
    {
        [$user, $organization, $scan] = $this->orgWithScan(['cis']);

        ScanResult::factory()->create([
            'scan_id' => $scan->id,
            'status' => 'resolved',
            'resolved_at' => now(),
            'finding_type' => 'cis-1.4',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $frameworks = collect($response->json('compliance.frameworks'))->keyBy('key');

        $this->assertSame(0, $frameworks['cis']['failingRules']);
        $this->assertSame(100, $frameworks['cis']['score']);
    }

    public function test_scan_without_rulesets_falls_back_to_basic(): void
    {
        [$user, $organization] = $this->orgWithScan(null);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $frameworks = collect($response->json('compliance.frameworks'))->keyBy('key');

        $this->assertTrue($frameworks['basic']['evaluated']);
        $this->assertFalse($frameworks['cis']['evaluated']);
    }

    public function test_reports_change_against_the_previous_period(): void
    {
        [$user, $organization, $scan] = $this->orgWithScan(null, now()->subDays(45));

        // Two findings inside the window...
        ScanResult::factory()->count(2)->create([
            'scan_id' => $scan->id,
            'created_at' => now()->subDays(5),
        ]);

        // ...against four in the preceding 30-day window.
        ScanResult::factory()->count(4)->create([
            'scan_id' => $scan->id,
            'created_at' => now()->subDays(40),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()
            ->assertJsonPath('summary.totalFindings', 2)
            ->assertJsonPath('summary.previousTotalFindings', 4)
            ->assertJsonPath('summary.findingsChangePercent', -50);
    }

    public function test_change_percent_is_null_without_a_baseline(): void
    {
        [$user, $organization, $scan] = $this->orgWithScan();

        ScanResult::factory()->create(['scan_id' => $scan->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()
            ->assertJsonPath('summary.previousTotalFindings', 0)
            ->assertJsonPath('summary.findingsChangePercent', null);
    }

    public function test_reports_zero_scans_for_an_empty_period(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()
            ->assertJsonPath('summary.scanCount', 0)
            ->assertJsonPath('summary.totalFindings', 0)
            ->assertJsonPath('summary.averageCompliance', null);
    }

    public function test_falls_back_to_thirty_days_for_an_unknown_period(): void
    {
        [$user, $organization] = $this->orgWithScan();

        $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=bogus")
            ->assertOk()
            ->assertJsonPath('period', '30d')
            ->assertJsonPath('granularity', 'day');
    }

    public function test_returns_403_when_user_cannot_view_insights_for_organization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights");

        $response->assertStatus(403);
    }
}
