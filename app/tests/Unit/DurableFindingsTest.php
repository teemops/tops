<?php

namespace Tests\Unit;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanDetail;
use App\Models\ScanResult;
use App\Services\RulesEngine\ConditionEvaluator;
use App\Services\RulesEngine\FindingsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

/**
 * D-1: a finding is one lasting record of one problem on one resource.
 *
 * The suite had no coverage of a second scan of the same account at all, which is how
 * findings came to accumulate across scans unnoticed. These tests are all about the
 * second scan.
 *
 * See docs/features/durable-findings.md.
 */
class DurableFindingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * The rule under test: an IAM user with no MFA device.
     */
    private function rules(string $severity = 'high'): array
    {
        return [
            'rules' => [
                [
                    'rule' => 'tops-iam-001',
                    'name' => 'IAM User MFA Token',
                    'service' => 'iam',
                    'method' => 'listMFADevices',
                    'description' => 'IAM Users should have MFA enabled',
                    'severity' => $severity,
                    'remediation' => 'Enable MFA for the IAM user.',
                    'condition' => "count(\$data['MFADevices'] ?? []) == 0",
                ],
            ],
        ];
    }

    /**
     * Run one scan of one IAM user against the rule above.
     *
     * @param  bool  $violates  Whether the rule fails for that user on this scan.
     */
    private function runScan(Organization $organization, AwsAccount $account, bool $violates, string $resourceId = 'alice'): Scan
    {
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $account->id,
            'status' => 'completed',
        ]);

        ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => $resourceId,
            'api_method' => 'listMFADevices',
            'raw_data' => ['MFADevices' => []],
        ]);

        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn(json_encode($this->rules()));

        $evaluator = Mockery::mock(ConditionEvaluator::class);
        $evaluator->shouldReceive('evaluate')->andReturn($violates);

        (new FindingsEngine($evaluator))->evaluateScan($scan, ['basic']);

        return $scan;
    }

    private function organizationWithAccount(): array
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        return [$organization, $account];
    }

    /**
     * The headline outcome. This is the bug D-1 exists to fix.
     */
    public function test_scanning_twice_with_nothing_fixed_does_not_double_the_findings(): void
    {
        [$organization, $account] = $this->organizationWithAccount();

        $this->runScan($organization, $account, violates: true);
        $this->assertSame(1, ScanResult::count());

        $second = $this->runScan($organization, $account, violates: true);

        $this->assertSame(1, ScanResult::count(), 'A second scan of an unchanged account must not add a second copy.');
        $this->assertSame($second->id, ScanResult::first()->last_seen_scan_id);
    }

    public function test_a_finding_records_when_it_was_first_and_last_seen(): void
    {
        [$organization, $account] = $this->organizationWithAccount();

        $first = $this->runScan($organization, $account, violates: true);
        $firstSeen = ScanResult::first()->first_seen_at;

        $second = $this->runScan($organization, $account, violates: true);
        $finding = ScanResult::first();

        $this->assertEquals($firstSeen, $finding->first_seen_at, 'First seen does not move.');
        $this->assertSame($first->id, $finding->scan_id, 'The originating scan is remembered.');
        $this->assertSame($second->id, $finding->last_seen_scan_id, 'Last seen follows the newest scan.');
    }

    /**
     * Examined, and no longer failing.
     */
    public function test_a_finding_is_resolved_as_fixed_when_a_later_scan_examines_it_and_it_passes(): void
    {
        [$organization, $account] = $this->organizationWithAccount();

        $this->runScan($organization, $account, violates: true);
        $this->assertSame('open', ScanResult::first()->status);

        $second = $this->runScan($organization, $account, violates: false);

        $finding = ScanResult::first();
        $this->assertSame('resolved', $finding->status);
        $this->assertSame(ScanResult::REASON_FIXED, $finding->resolution_reason);
        $this->assertNotNull($finding->resolved_at);
        $this->assertSame($second->id, $finding->last_seen_scan_id);
    }

    /**
     * Absence is not evidence. This is what keeps per-service scans (F-2) safe.
     */
    public function test_a_scan_that_does_not_examine_a_resource_leaves_its_finding_alone(): void
    {
        [$organization, $account] = $this->organizationWithAccount();

        $this->runScan($organization, $account, violates: true, resourceId: 'alice');
        $original = ScanResult::first();

        // A later scan that collects nothing for alice — she was not examined.
        $second = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $account->id,
            'status' => 'completed',
        ]);

        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn(json_encode($this->rules()));

        $evaluator = Mockery::mock(ConditionEvaluator::class);
        $evaluator->shouldReceive('evaluate')->andReturn(false);

        (new FindingsEngine($evaluator))->evaluateScan($second, ['basic']);

        $finding = ScanResult::first();
        $this->assertSame('open', $finding->status, 'Not looking must never resolve a finding.');
        $this->assertNull($finding->resolution_reason);
        $this->assertSame($original->last_seen_scan_id, $finding->last_seen_scan_id, 'An unexamined finding is untouched.');
    }

    /**
     * Status belongs to the user.
     */
    public function test_an_ignored_finding_stays_ignored_when_a_later_scan_still_finds_it(): void
    {
        [$organization, $account] = $this->organizationWithAccount();

        $this->runScan($organization, $account, violates: true);
        ScanResult::first()->update(['status' => 'ignored']);

        $this->runScan($organization, $account, violates: true);

        $this->assertSame('ignored', ScanResult::first()->status, 'A standing instruction is not overturned by finding the same thing again.');
        $this->assertSame(1, ScanResult::count());
    }

    public function test_a_resolved_finding_is_reopened_when_a_later_scan_still_finds_it(): void
    {
        [$organization, $account] = $this->organizationWithAccount();

        $this->runScan($organization, $account, violates: true);
        ScanResult::first()->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_reason' => ScanResult::REASON_MANUAL,
        ]);

        $this->runScan($organization, $account, violates: true);

        $finding = ScanResult::first();
        $this->assertSame('open', $finding->status, 'The claim was contradicted by evidence, and saying so is the point.');
        $this->assertNull($finding->resolved_at);
        $this->assertNull($finding->resolution_reason);
    }

    public function test_an_ignored_finding_is_resolved_when_a_later_scan_finds_it_compliant(): void
    {
        [$organization, $account] = $this->organizationWithAccount();

        $this->runScan($organization, $account, violates: true);
        ScanResult::first()->update(['status' => 'ignored']);

        $this->runScan($organization, $account, violates: false);

        $finding = ScanResult::first();
        $this->assertSame('resolved', $finding->status);
        $this->assertSame(ScanResult::REASON_FIXED, $finding->resolution_reason);
    }

    /**
     * Multi-tenancy. The identity includes the organization, so two tenants scanning the
     * same AWS account never touch each other's findings.
     */
    public function test_two_organizations_scanning_the_same_aws_account_do_not_share_findings(): void
    {
        $orgA = Organization::factory()->create();
        $accountA = AwsAccount::factory()->completed()->create([
            'organization_id' => $orgA->id,
            'aws_account_id' => '111122223333',
        ]);

        $orgB = Organization::factory()->create();
        $accountB = AwsAccount::factory()->completed()->create([
            'organization_id' => $orgB->id,
            'aws_account_id' => '111122223333',
        ]);

        $this->runScan($orgA, $accountA, violates: true);
        $this->runScan($orgB, $accountB, violates: true);

        $this->assertSame(2, ScanResult::count(), 'Same resource, same rule, different tenants — two findings.');
        $this->assertSame(1, ScanResult::where('organization_id', $orgA->id)->count());
        $this->assertSame(1, ScanResult::where('organization_id', $orgB->id)->count());

        // And one tenant resolving theirs does not resolve the other's.
        $this->runScan($orgA, $accountA, violates: false);

        $this->assertSame('resolved', ScanResult::where('organization_id', $orgA->id)->first()->status);
        $this->assertSame('open', ScanResult::where('organization_id', $orgB->id)->first()->status);
    }
}
