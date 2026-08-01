<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AwsAccount $awsAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['user_id' => $this->user->id]);
        $this->awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * Only findings from completed scans show up in the org-wide list.
     */
    private function completedScan(): Scan
    {
        return Scan::factory()->create([
            'organization_id' => $this->organization->id,
            'aws_account_id' => $this->awsAccount->id,
            'status' => 'completed',
        ]);
    }

    private function finding(Scan $scan, array $attributes = []): ScanResult
    {
        return ScanResult::factory()->create(array_merge([
            'scan_id' => $scan->id,
        ], $attributes));
    }

    private function findingsUrl(array $query = []): string
    {
        $url = "/api/organizations/{$this->organization->org_id}/findings";

        return $query ? $url.'?'.http_build_query($query) : $url;
    }

    public function test_listing_findings_requires_authentication(): void
    {
        $this->getJson($this->findingsUrl())->assertStatus(401);
    }

    public function test_a_non_member_cannot_list_findings(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->findingsUrl())
            ->assertStatus(403);
    }

    /**
     * Findings are visible to every role, including viewers.
     */
    public function test_a_viewer_can_list_findings(): void
    {
        $viewer = User::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ]);
        $this->finding($this->completedScan());

        $this->actingAs($viewer)->getJson($this->findingsUrl())->assertOk();
    }

    public function test_it_returns_findings_with_their_aws_account(): void
    {
        $scan = $this->completedScan();
        $finding = $this->finding($scan, ['title' => 'Bucket is public']);

        $response = $this->actingAs($this->user)->getJson($this->findingsUrl());

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals($finding->id, $response->json('findings.0.id'));
        $this->assertEquals('Bucket is public', $response->json('findings.0.title'));
        $this->assertEquals($this->awsAccount->id, $response->json('findings.0.awsAccountId'));
        $this->assertEquals($this->awsAccount->name, $response->json('findings.0.awsAccountName'));
    }

    public function test_findings_from_incomplete_scans_are_excluded(): void
    {
        $running = Scan::factory()->create([
            'organization_id' => $this->organization->id,
            'aws_account_id' => $this->awsAccount->id,
            'status' => 'running',
        ]);
        $this->finding($running);

        $response = $this->actingAs($this->user)->getJson($this->findingsUrl());

        $this->assertEquals(0, $response->json('total'));
    }

    public function test_findings_from_other_organizations_are_excluded(): void
    {
        $this->finding(Scan::factory()->create(['status' => 'completed']));

        $response = $this->actingAs($this->user)->getJson($this->findingsUrl());

        $this->assertEquals(0, $response->json('total'));
    }

    public function test_findings_are_sorted_most_severe_first(): void
    {
        $scan = $this->completedScan();
        foreach (['low', 'critical', 'medium', 'high'] as $severity) {
            $this->finding($scan, ['severity' => $severity]);
        }

        $response = $this->actingAs($this->user)->getJson($this->findingsUrl());

        $this->assertEquals(
            ['critical', 'high', 'medium', 'low'],
            array_column($response->json('findings'), 'severity')
        );
    }

    public function test_findings_can_be_filtered(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['service' => 's3', 'finding_type' => 'tops-s3-001', 'status' => 'open']);
        $this->finding($scan, ['service' => 'iam', 'finding_type' => 'tops-iam-001', 'status' => 'resolved']);

        $byService = $this->actingAs($this->user)->getJson($this->findingsUrl(['service' => 's3']));
        $this->assertEquals(1, $byService->json('total'));
        $this->assertEquals('s3', $byService->json('findings.0.service'));

        $byType = $this->actingAs($this->user)->getJson($this->findingsUrl(['finding_type' => 'tops-iam-001']));
        $this->assertEquals(1, $byType->json('total'));

        $byStatus = $this->actingAs($this->user)->getJson($this->findingsUrl(['status' => 'resolved']));
        $this->assertEquals(1, $byStatus->json('total'));

        $byAccount = $this->actingAs($this->user)
            ->getJson($this->findingsUrl(['aws_account_id' => $this->awsAccount->id]));
        $this->assertEquals(2, $byAccount->json('total'));
    }

    /**
     * The pills: one per service that has findings, busiest first, and nothing for a
     * service with none.
     */
    public function test_service_facets_are_counted_and_ordered_by_size(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['service' => 's3']);
        $this->finding($scan, ['service' => 'ec2']);
        $this->finding($scan, ['service' => 'ec2']);
        $this->finding($scan, ['service' => 'ec2']);
        $this->finding($scan, ['service' => 'iam']);
        $this->finding($scan, ['service' => 'iam']);

        $facets = $this->actingAs($this->user)->getJson($this->findingsUrl())->json('serviceFacets');

        $this->assertSame([
            ['service' => 'ec2', 'count' => 3],
            ['service' => 'iam', 'count' => 2],
            ['service' => 's3', 'count' => 1],
        ], $facets);
    }

    /**
     * The rule that makes faceting usable: the service filter must not constrain its own
     * facet, or picking one pill zeroes the rest and there is no way to switch.
     */
    public function test_the_service_filter_does_not_constrain_its_own_facet(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['service' => 'ec2']);
        $this->finding($scan, ['service' => 'ec2']);
        $this->finding($scan, ['service' => 's3']);

        $facets = $this->actingAs($this->user)
            ->getJson($this->findingsUrl(['service' => 'ec2']))
            ->json('serviceFacets');

        $this->assertSame([
            ['service' => 'ec2', 'count' => 2],
            ['service' => 's3', 'count' => 1],
        ], $facets);
    }

    /**
     * Every other filter *does* narrow the facets — that is what makes the counts mean
     * "what you would get", rather than "what exists somewhere".
     */
    public function test_service_facets_respect_the_other_active_filters(): void
    {
        $scan = $this->completedScan();
        $otherAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $this->organization->id,
        ]);
        $otherScan = Scan::factory()->create([
            'organization_id' => $this->organization->id,
            'aws_account_id' => $otherAccount->id,
            'status' => 'completed',
        ]);

        $this->finding($scan, ['service' => 'ec2', 'status' => 'open']);
        $this->finding($scan, ['service' => 's3', 'status' => 'ignored']);
        $this->finding($otherScan, ['service' => 'ec2']);

        $byAccount = $this->actingAs($this->user)
            ->getJson($this->findingsUrl(['aws_account_id' => $this->awsAccount->id]))
            ->json('serviceFacets');

        $this->assertSame([
            ['service' => 'ec2', 'count' => 1],
            ['service' => 's3', 'count' => 1],
        ], $byAccount);

        $byStatus = $this->actingAs($this->user)
            ->getJson($this->findingsUrl(['status' => 'ignored']))
            ->json('serviceFacets');

        $this->assertSame([['service' => 's3', 'count' => 1]], $byStatus);
    }

    /**
     * A pill reading N must produce N rows when clicked. The summary drops resolved
     * findings and the list does not, so the facets have to follow the list.
     */
    public function test_a_service_facet_count_matches_the_rows_that_pill_returns(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['service' => 'ec2', 'status' => 'open']);
        $this->finding($scan, ['service' => 'ec2', 'status' => 'resolved']);
        $this->finding($scan, ['service' => 'ec2', 'status' => 'ignored']);

        $facets = $this->actingAs($this->user)->getJson($this->findingsUrl())->json('serviceFacets');
        $this->assertSame([['service' => 'ec2', 'count' => 3]], $facets);

        $rows = $this->actingAs($this->user)->getJson($this->findingsUrl(['service' => 'ec2']));
        $this->assertEquals(3, $rows->json('total'));
    }

    public function test_service_facets_exclude_other_organizations(): void
    {
        $this->finding(
            Scan::factory()->create(['status' => 'completed']),
            ['service' => 'ec2']
        );
        $this->finding($this->completedScan(), ['service' => 's3']);

        $facets = $this->actingAs($this->user)->getJson($this->findingsUrl())->json('serviceFacets');

        $this->assertSame([['service' => 's3', 'count' => 1]], $facets);
    }

    /**
     * Findings the list itself excludes must not appear as a pill, or the pill leads
     * somewhere empty.
     */
    public function test_service_facets_exclude_incomplete_scans(): void
    {
        $pending = Scan::factory()->create([
            'organization_id' => $this->organization->id,
            'aws_account_id' => $this->awsAccount->id,
            'status' => 'pending',
        ]);
        $this->finding($pending, ['service' => 'ec2']);
        $this->finding($this->completedScan(), ['service' => 's3']);

        $facets = $this->actingAs($this->user)->getJson($this->findingsUrl())->json('serviceFacets');

        $this->assertSame([['service' => 's3', 'count' => 1]], $facets);
    }

    public function test_findings_can_be_filtered_by_benchmark(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['service' => 's3', 'rulesets' => ['basic']]);
        $this->finding($scan, ['service' => 'ec2', 'rulesets' => ['cis']]);

        $response = $this->actingAs($this->user)->getJson($this->findingsUrl(['ruleset' => 'cis']));

        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('ec2', $response->json('findings.0.service'));
    }

    /**
     * A rule can belong to more than one ruleset, and such a finding is a gap under both.
     */
    public function test_a_finding_in_two_benchmarks_matches_either(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['rulesets' => ['basic', 'cis']]);

        foreach (['basic', 'cis'] as $ruleset) {
            $this->assertEquals(
                1,
                $this->actingAs($this->user)->getJson($this->findingsUrl(['ruleset' => $ruleset]))->json('total')
            );
        }
    }

    /**
     * Findings raised before F-4 have no benchmark. They must not be guessed into one —
     * a finding wrongly labelled CIS is worse than one honestly labelled nothing.
     */
    public function test_findings_from_before_this_shipped_match_no_benchmark(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['finding_type' => 'cis-5.1', 'rulesets' => null]);

        $this->assertEquals(
            0,
            $this->actingAs($this->user)->getJson($this->findingsUrl(['ruleset' => 'cis']))->json('total')
        );

        // Still visible unfiltered — blank benchmark, not hidden.
        $this->assertEquals(1, $this->actingAs($this->user)->getJson($this->findingsUrl())->json('total'));
    }

    public function test_benchmark_facets_count_what_each_would_return(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['rulesets' => ['basic']]);
        $this->finding($scan, ['rulesets' => ['basic']]);
        $this->finding($scan, ['rulesets' => ['cis']]);

        $facets = $this->actingAs($this->user)->getJson($this->findingsUrl())->json('benchmarkFacets');

        $this->assertSame(
            [
                ['ruleset' => 'basic', 'label' => 'Basic', 'count' => 2],
                ['ruleset' => 'cis', 'label' => 'CIS', 'count' => 1],
            ],
            $facets
        );
    }

    /**
     * PCI has no rules, so it is never offered — the same rule the New Scan modal follows.
     */
    public function test_a_benchmark_with_no_findings_is_not_offered(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['rulesets' => ['basic']]);

        $facets = $this->actingAs($this->user)->getJson($this->findingsUrl())->json('benchmarkFacets');

        $this->assertSame(['basic'], array_column($facets, 'ruleset'));
    }

    /**
     * Same rule as the service facet: a filter must not constrain its own facet, or
     * selecting one benchmark leaves no way to reach the other.
     */
    public function test_the_benchmark_filter_does_not_constrain_its_own_facet(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['rulesets' => ['basic']]);
        $this->finding($scan, ['rulesets' => ['cis']]);

        $facets = $this->actingAs($this->user)
            ->getJson($this->findingsUrl(['ruleset' => 'cis']))
            ->json('benchmarkFacets');

        $this->assertSame(['basic', 'cis'], array_column($facets, 'ruleset'));
    }

    public function test_benchmark_and_service_filters_combine(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['service' => 'ec2', 'rulesets' => ['cis']]);
        $this->finding($scan, ['service' => 's3', 'rulesets' => ['cis']]);
        $this->finding($scan, ['service' => 'ec2', 'rulesets' => ['basic']]);

        $response = $this->actingAs($this->user)
            ->getJson($this->findingsUrl(['ruleset' => 'cis', 'service' => 'ec2']));

        $this->assertEquals(1, $response->json('total'));
    }

    public function test_benchmark_facets_exclude_other_organizations(): void
    {
        ScanResult::factory()->create([
            'scan_id' => Scan::factory()->create(['status' => 'completed'])->id,
            'rulesets' => ['cis'],
        ]);
        $this->finding($this->completedScan(), ['rulesets' => ['basic']]);

        $facets = $this->actingAs($this->user)->getJson($this->findingsUrl())->json('benchmarkFacets');

        $this->assertSame(['basic'], array_column($facets, 'ruleset'));
    }

    public function test_findings_are_paginated(): void
    {
        $scan = $this->completedScan();
        ScanResult::factory()->count(10)->create(['scan_id' => $scan->id]);

        $response = $this->actingAs($this->user)->getJson($this->findingsUrl(['limit' => 4, 'offset' => 2]));

        $this->assertEquals(10, $response->json('total'));
        $this->assertEquals(4, $response->json('limit'));
        $this->assertEquals(2, $response->json('offset'));
        $this->assertCount(4, $response->json('findings'));
    }

    public function test_the_page_size_is_capped(): void
    {
        $response = $this->actingAs($this->user)->getJson($this->findingsUrl(['limit' => 5000]));

        $this->assertEquals(200, $response->json('limit'));
    }

    /**
     * A clean organization scores 100.
     */
    /**
     * D-1: fixing something has to move the numbers, or the scan tells you nothing.
     */
    public function test_resolved_findings_do_not_count_toward_the_totals_or_the_score(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);
        $awsAccount = AwsAccount::factory()->completed()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'status' => 'completed',
        ]);

        ScanResult::factory()->create(['scan_id' => $scan->id, 'severity' => 'critical', 'status' => 'open']);
        ScanResult::factory()->create(['scan_id' => $scan->id, 'severity' => 'critical', 'status' => 'resolved']);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/findings");

        $response->assertOk()
            ->assertJsonPath('summary.bySeverity.critical', 1)
            ->assertJsonPath('summary.total', 1);
    }

    /**
     * Ignoring is a decision not to act, not evidence the problem went away — so it must
     * not be a way to improve the score.
     */
    public function test_ignored_findings_still_count_toward_the_totals(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);
        $awsAccount = AwsAccount::factory()->completed()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'status' => 'completed',
        ]);

        ScanResult::factory()->create(['scan_id' => $scan->id, 'severity' => 'high', 'status' => 'open']);
        ScanResult::factory()->create(['scan_id' => $scan->id, 'severity' => 'high', 'status' => 'ignored']);

        $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/findings")
            ->assertOk()
            ->assertJsonPath('summary.bySeverity.high', 2);
    }

    public function test_an_organization_with_no_findings_reports_zero(): void
    {
        $response = $this->actingAs($this->user)->getJson($this->findingsUrl());

        $this->assertEquals(0, $response->json('summary.total'));
        $this->assertEquals(
            ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0],
            $response->json('summary.bySeverity')
        );
    }

    public function test_the_summary_counts_findings_by_severity(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, ['severity' => 'critical']);
        $this->finding($scan, ['severity' => 'high']);
        $this->finding($scan, ['severity' => 'medium']);
        $this->finding($scan, ['severity' => 'low']);

        $response = $this->actingAs($this->user)->getJson($this->findingsUrl());

        $this->assertEquals(4, $response->json('summary.total'));
        $this->assertEquals(
            ['critical' => 1, 'high' => 1, 'medium' => 1, 'low' => 1],
            $response->json('summary.bySeverity')
        );
    }

    /**
     * The score this replaced read 0 for any account with more than a handful of findings
     * and could not move. Counts keep counting. See D-12.
     */
    public function test_the_summary_keeps_counting_past_where_the_old_score_bottomed_out(): void
    {
        $scan = $this->completedScan();
        ScanResult::factory()->count(15)->create([
            'scan_id' => $scan->id,
            'severity' => 'critical',
        ]);

        $response = $this->actingAs($this->user)->getJson($this->findingsUrl());

        $this->assertEquals(15, $response->json('summary.total'));
        $this->assertEquals(15, $response->json('summary.bySeverity.critical'));
        $this->assertNull($response->json('summary.securityScore'));
    }

    public function test_it_shows_a_single_finding_with_its_recommendation(): void
    {
        $finding = $this->finding($this->completedScan(), ['finding_type' => 'tops-s3-001']);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-Id', $this->organization->org_id)
            ->getJson("/api/results/{$finding->id}");

        $response->assertOk()
            ->assertJson(['id' => $finding->id, 'findingType' => 'tops-s3-001']);
        $this->assertNotNull($response->json('recommendation'));
    }

    public function test_a_finding_from_another_organization_is_not_found(): void
    {
        $finding = $this->finding(Scan::factory()->create(['status' => 'completed']));

        $this->actingAs($this->user)
            ->withHeader('X-Organization-Id', $this->organization->org_id)
            ->getJson("/api/results/{$finding->id}")
            ->assertStatus(404);
    }

    public function test_a_finding_can_be_resolved(): void
    {
        $finding = $this->finding($this->completedScan(), ['status' => 'open']);

        $this->actingAs($this->user)
            ->withHeader('X-Organization-Id', $this->organization->org_id)
            ->putJson("/api/results/{$finding->id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJson(['status' => 'resolved']);

        $finding->refresh();
        $this->assertEquals('resolved', $finding->status);
        $this->assertNotNull($finding->resolved_at);
    }

    public function test_reopening_a_finding_clears_the_resolved_timestamp(): void
    {
        $finding = $this->finding($this->completedScan(), [
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->withHeader('X-Organization-Id', $this->organization->org_id)
            ->putJson("/api/results/{$finding->id}", ['status' => 'open'])
            ->assertOk();

        $this->assertNull($finding->fresh()->resolved_at);
    }

    public function test_an_unknown_finding_status_is_rejected(): void
    {
        $finding = $this->finding($this->completedScan());

        $this->actingAs($this->user)
            ->withHeader('X-Organization-Id', $this->organization->org_id)
            ->putJson("/api/results/{$finding->id}", ['status' => 'wontfix'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_it_groups_findings_by_type(): void
    {
        $scan = $this->completedScan();
        $this->finding($scan, [
            'finding_type' => 'tops-s3-001',
            'title' => 'Bucket allows public access',
            'severity' => 'high',
        ]);
        $this->finding($scan, ['finding_type' => 'tops-s3-001', 'severity' => 'critical']);
        $this->finding($scan, ['finding_type' => 'tops-iam-001']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/organizations/{$this->organization->org_id}/findings/by-type/tops-s3-001");

        $response->assertOk();
        $this->assertEquals('tops-s3-001', $response->json('findingType'));
        $this->assertEquals(2, $response->json('total'));
        $this->assertEquals('critical', $response->json('findings.0.severity'));
        $this->assertNotNull($response->json('recommendation'));
    }

    public function test_grouping_by_an_unknown_type_returns_an_empty_set(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/organizations/{$this->organization->org_id}/findings/by-type/tops-nope-999");

        $response->assertOk();
        $this->assertEquals(0, $response->json('total'));
        $this->assertEquals('tops-nope-999', $response->json('title'));
        $this->assertNull($response->json('recommendation'));
    }

    public function test_it_returns_the_recommendations_catalogue(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-Id', $this->organization->org_id)
            ->getJson('/api/recommendations');

        $response->assertOk();
        $this->assertNotEmpty($response->json('recommendations'));
    }

    public function test_a_non_member_cannot_read_the_recommendations_catalogue(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeader('X-Organization-Id', $this->organization->org_id)
            ->getJson('/api/recommendations')
            ->assertStatus(403);
    }
}
