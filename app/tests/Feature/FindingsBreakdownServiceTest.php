<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Models\User;
use App\Services\FindingsBreakdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindingsBreakdownServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private AwsAccount $awsAccount;

    private Scan $scan;

    private FindingsBreakdownService $breakdown;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->organization = Organization::factory()->create(['user_id' => $user->id]);
        $this->awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->scan = Scan::factory()->completed()->create([
            'organization_id' => $this->organization->id,
            'aws_account_id' => $this->awsAccount->id,
        ]);

        $this->breakdown = app(FindingsBreakdownService::class);
    }

    /**
     * Findings carry their own organization and account since D-1, so the breakdown reads
     * them directly rather than through the scan.
     */
    private function finding(array $attributes = [], ?AwsAccount $account = null): ScanResult
    {
        return ScanResult::factory()->create(array_merge([
            'scan_id' => $this->scan->id,
            'organization_id' => $this->organization->id,
            'aws_account_id' => ($account ?? $this->awsAccount)->id,
            'status' => 'open',
        ], $attributes));
    }

    private function for(?string $awsAccountId = null): array
    {
        return $this->breakdown->for($this->organization->id, $awsAccountId ?? $this->awsAccount->id);
    }

    public function test_it_groups_by_service_largest_first_with_a_severity_split(): void
    {
        $this->finding(['service' => 'ec2', 'severity' => 'high']);
        $this->finding(['service' => 'ec2', 'severity' => 'high']);
        $this->finding(['service' => 'ec2', 'severity' => 'medium']);
        $this->finding(['service' => 's3', 'severity' => 'low']);

        $byService = $this->for()['byService'];

        $this->assertSame('ec2', $byService[0]['key']);
        $this->assertSame(3, $byService[0]['total']);
        $this->assertSame(
            ['critical' => 0, 'high' => 2, 'medium' => 1, 'low' => 0],
            $byService[0]['severities']
        );

        $this->assertSame('s3', $byService[1]['key']);
        $this->assertSame(1, $byService[1]['total']);
    }

    public function test_it_groups_by_finding_type_as_well(): void
    {
        $this->finding(['finding_type' => 'cis-5.1', 'severity' => 'high']);
        $this->finding(['finding_type' => 'cis-5.1', 'severity' => 'high']);
        $this->finding(['finding_type' => 'cis-3.9', 'severity' => 'medium']);

        $byType = $this->for()['byFindingType'];

        $this->assertSame('cis-5.1', $byType[0]['key']);
        $this->assertSame(2, $byType[0]['total']);
        $this->assertSame('cis-3.9', $byType[1]['key']);
    }

    /**
     * The rule the wireframe calls out: eighteen mediums must not outrank eighteen highs.
     */
    public function test_fix_first_ranks_by_severity_rather_than_raw_count(): void
    {
        foreach (range(1, 6) as $i) {
            $this->finding(['finding_type' => 'many-mediums', 'severity' => 'medium']);
        }

        foreach (range(1, 4) as $i) {
            $this->finding(['finding_type' => 'fewer-criticals', 'severity' => 'critical']);
        }

        $fixFirst = $this->for()['fixFirst'];

        $this->assertSame('critical', $fixFirst[0]['topSeverity']);
        $this->assertSame(4, $fixFirst[0]['clears']);
        $this->assertSame(6, $fixFirst[1]['clears']);
    }

    /**
     * A group is labelled with the worst thing in it — one critical among lows is still a
     * critical job.
     */
    public function test_a_fix_first_entry_is_labelled_with_its_worst_severity(): void
    {
        $this->finding(['finding_type' => 'mixed', 'severity' => 'low']);
        $this->finding(['finding_type' => 'mixed', 'severity' => 'critical']);

        $this->assertSame('critical', $this->for()['fixFirst'][0]['topSeverity']);
    }

    public function test_resolved_findings_are_not_open_problems_but_ignored_ones_are(): void
    {
        $this->finding(['service' => 'ec2', 'status' => 'open']);
        $this->finding(['service' => 'ec2', 'status' => 'ignored']);
        $this->finding(['service' => 'ec2', 'status' => 'resolved']);

        $result = $this->for();

        $this->assertSame(2, $result['summary']['total']);
        $this->assertSame(2, $result['byService'][0]['total']);
    }

    public function test_the_summary_totals_agree_with_the_breakdown(): void
    {
        $this->finding(['severity' => 'critical']);
        $this->finding(['severity' => 'high']);
        $this->finding(['severity' => 'high']);
        $this->finding(['severity' => 'low']);

        $summary = $this->for()['summary'];

        $this->assertSame(4, $summary['total']);
        $this->assertSame(
            ['critical' => 1, 'high' => 2, 'medium' => 0, 'low' => 1],
            $summary['bySeverity']
        );
    }

    public function test_another_organizations_findings_are_never_counted(): void
    {
        $otherOrg = Organization::factory()->create(['user_id' => User::factory()->create()->id]);
        ScanResult::factory()->create([
            'scan_id' => Scan::factory()->completed()->create(['organization_id' => $otherOrg->id])->id,
            'organization_id' => $otherOrg->id,
            'aws_account_id' => $this->awsAccount->id,
            'service' => 'ec2',
            'status' => 'open',
        ]);

        $this->finding(['service' => 's3']);

        $byService = $this->for()['byService'];

        $this->assertCount(1, $byService);
        $this->assertSame('s3', $byService[0]['key']);
    }

    public function test_another_account_in_the_same_organization_is_not_counted(): void
    {
        $otherAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->finding(['service' => 'ec2'], $otherAccount);
        $this->finding(['service' => 's3']);

        $byService = $this->for()['byService'];

        $this->assertCount(1, $byService);
        $this->assertSame('s3', $byService[0]['key']);
    }

    /**
     * Insights (F-5) needs the same breakdown keyed on the organization rather than one
     * account. Passing no account is what makes that reuse possible.
     */
    public function test_omitting_the_account_covers_the_whole_organization(): void
    {
        $otherAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->finding(['service' => 'ec2'], $otherAccount);
        $this->finding(['service' => 's3']);

        $byService = $this->breakdown->for($this->organization->id)['byService'];

        $this->assertCount(2, $byService);
        $this->assertSame(2, $this->breakdown->for($this->organization->id)['summary']['total']);
    }
}
