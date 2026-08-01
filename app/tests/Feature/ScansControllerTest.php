<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ScansControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    /**
     * Test creating a scan with scan_types
     */
    public function test_can_create_scan_with_scan_types(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_types' => ['iam', 's3'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'awsAccountId',
                'scanTypes',
                'status',
                'createdAt',
            ]);

        $this->assertDatabaseHas('scans', [
            'aws_account_id' => $awsAccount->id,
            'status' => 'pending',
        ]);

        $scan = Scan::where('aws_account_id', $awsAccount->id)->first();
        $this->assertEquals(['iam', 's3'], $scan->scan_types);

        // Assert job was dispatched
        Bus::assertDispatched(\App\Jobs\ProcessAuditScanJob::class, function ($job) use ($scan) {
            return $job->scan->id === $scan->id;
        });
    }

    /**
     * Test creating a scan with EC2 scan type
     */
    public function test_can_create_scan_with_ec2_type(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_types' => ['ec2'],
            ]);

        $response->assertStatus(201);

        $scan = Scan::where('aws_account_id', $awsAccount->id)->first();
        $this->assertEquals(['ec2'], $scan->scan_types);
    }

    /**
     * Test creating a scan with all scan types
     */
    public function test_can_create_scan_with_all_types(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_types' => ['ec2', 'iam', 's3'],
            ]);

        $response->assertStatus(201);

        $scan = Scan::where('aws_account_id', $awsAccount->id)->first();
        $this->assertEquals(['ec2', 'iam', 's3'], $scan->scan_types);
    }

    /**
     * Test a scan request must select something to scan
     *
     * Since scan profiles were introduced, both scan_profiles and scan_types are
     * individually optional; the cross-field check reports a missing selection
     * against scan_profiles.
     */
    public function test_validation_requires_scan_types(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scan_profiles']);
    }

    /**
     * Test validation requires at least one scan type
     */
    public function test_validation_requires_at_least_one_scan_type(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_types' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scan_profiles']);
    }

    /**
     * Test a scan can be created from a scan profile, which expands to services
     */
    public function test_can_create_scan_from_a_scan_profile(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_profiles' => ['basic'],
            ]);

        $response->assertStatus(201);

        $scan = Scan::where('aws_account_id', $awsAccount->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(
            \App\Services\ScanProfilesService::servicesFor(['basic']),
            $scan->scan_types
        );
    }

    /**
     * Narrowing a profile to a subset of its services — the point of F-2.
     */
    public function test_a_profile_can_be_narrowed_to_individual_services(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_profiles' => ['basic'],
                'scan_types' => ['s3', 'ec2'],
            ]);

        $response->assertStatus(201);

        $scan = Scan::where('aws_account_id', $awsAccount->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(['s3', 'ec2'], $scan->scan_types);
        // The ruleset comes from the profile, not the hardcoded 'basic' the
        // explicit-services path used to fall back to.
        $this->assertEqualsCanonicalizing(['basic'], $scan->rulesets);
    }

    /**
     * The case that could not be expressed before: one service, a non-basic ruleset.
     */
    public function test_a_single_service_can_be_scanned_against_a_non_basic_ruleset(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_profiles' => ['cis'],
                'scan_types' => ['s3'],
            ]);

        $response->assertStatus(201);

        $scan = Scan::where('aws_account_id', $awsAccount->id)->firstOrFail();
        $this->assertSame(['s3'], $scan->scan_types);
        $this->assertSame(['cis'], $scan->rulesets);
    }

    /**
     * Omitting scan_types must keep the pre-F-2 behaviour exactly.
     */
    public function test_a_profile_without_services_still_scans_everything_it_covers(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_profiles' => ['cis'],
            ]);

        $response->assertStatus(201);

        $scan = Scan::where('aws_account_id', $awsAccount->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(
            \App\Services\ScanProfilesService::servicesFor(['cis']),
            $scan->scan_types
        );
    }

    /**
     * A service the profile has no rules for would scan and find nothing, which reads
     * as "compliant". Reject it instead.
     */
    public function test_a_service_the_profile_has_no_rules_for_is_rejected(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        // dynamodb is a real service with basic rules, but CIS has none for it.
        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_profiles' => ['cis'],
                'scan_types' => ['dynamodb'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('scan_types');

        $this->assertDatabaseCount('scans', 0);
    }

    /**
     * Narrowing must not become a way to reach another organization's account.
     */
    public function test_cannot_narrow_a_scan_onto_another_organizations_account(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create(['user_id' => $user->id]);

        $otherOrganization = Organization::factory()->create();
        $otherAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$otherOrganization->org_id}/scans", [
                'aws_account_id' => $otherAccount->id,
                'scan_profiles' => ['basic'],
                'scan_types' => ['s3'],
            ]);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseCount('scans', 0);
    }

    /**
     * Test an unknown scan profile is rejected
     */
    public function test_validation_rejects_an_unknown_scan_profile(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_profiles' => ['not-a-profile'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scan_profiles.0']);
    }

    /**
     * Test validation requires valid scan type values
     */
    public function test_validation_requires_valid_scan_type_values(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_types' => ['invalid-type'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scan_types.0']);
    }

    /**
     * Test cannot create scan for non-completed AWS account
     */
    public function test_cannot_create_scan_for_non_completed_account(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->pending()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/scans", [
                'aws_account_id' => $awsAccount->id,
                'scan_types' => ['iam'],
            ]);

        $response->assertStatus(404);
    }

    /**
     * Test listing scans includes scan_types
     */
    public function test_listing_scans_includes_scan_types(): void
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
            'scan_types' => ['iam', 's3'],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/scans");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'scans' => [
                    '*' => [
                        'id',
                        'awsAccountId',
                        'awsAccountName',
                        'status',
                        'findingsCount',
                        'createdAt',
                    ],
                ],
                'total',
            ]);
    }

    /**
     * Test filtering scans by AWS account
     */
    public function test_can_filter_scans_by_aws_account(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount1 = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $awsAccount2 = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan1 = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount1->id,
        ]);
        $scan2 = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount2->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/scans?aws_account_id={$awsAccount1->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(1, $data['total']);
        $this->assertEquals($scan1->id, $data['scans'][0]['id']);
    }

    /**
     * Test filtering scans by status
     */
    public function test_can_filter_scans_by_status(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan1 = Scan::factory()->completed()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);
        $scan2 = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/scans?status=completed");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(1, $data['total']);
        $this->assertEquals($scan1->id, $data['scans'][0]['id']);
    }

    /**
     * Test filtering scans by scan_type
     */
    public function test_can_filter_scans_by_scan_type(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan1 = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['iam', 's3'],
        ]);
        $scan2 = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/scans?scan_type=iam");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(1, $data['total']);
        $this->assertEquals($scan1->id, $data['scans'][0]['id']);
    }

    /**
     * Test showing a specific scan
     */
    public function test_can_show_scan(): void
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
            'scan_types' => ['iam', 's3'],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/scans/{$scan->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'awsAccountId',
                'awsAccountName',
                'status',
                'findingsCount',
                'createdAt',
                'startedAt',
                'completedAt',
            ]);

        $this->assertEquals($scan->id, $response->json('id'));
    }

    /**
     * Test cannot show scan from another user's organization
     */
    public function test_cannot_show_scan_from_other_organization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/scans/{$scan->id}");

        $response->assertStatus(404);
    }

    /**
     * Test getting scan results
     */
    public function test_can_get_scan_results(): void
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

        // Create some scan results
        \App\Models\ScanResult::factory()->critical()->count(2)->create(['scan_id' => $scan->id]);
        \App\Models\ScanResult::factory()->high()->count(3)->create(['scan_id' => $scan->id]);
        \App\Models\ScanResult::factory()->create(['scan_id' => $scan->id, 'severity' => 'medium']);

        $response = $this->actingAs($user)
            ->getJson("/api/scans/{$scan->id}/results");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'scanId',
                'findings',
                'summary' => [
                    'total',
                    'critical',
                    'high',
                    'medium',
                    'low',
                ],
                'total',
                'limit',
                'offset',
            ]);

        $data = $response->json();
        $this->assertEquals($scan->id, $data['scanId']);
        $this->assertEquals(6, $data['total']);
        $this->assertEquals(2, $data['summary']['critical']);
        $this->assertEquals(3, $data['summary']['high']);
        $this->assertEquals(1, $data['summary']['medium']);
    }

    /**
     * Test filtering scan results by severity
     */
    public function test_can_filter_scan_results_by_severity(): void
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

        \App\Models\ScanResult::factory()->critical()->count(2)->create(['scan_id' => $scan->id]);
        \App\Models\ScanResult::factory()->high()->count(3)->create(['scan_id' => $scan->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/scans/{$scan->id}/results?severity=critical");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(2, $data['total']);
    }

    /**
     * Test filtering scan results by service
     */
    public function test_can_filter_scan_results_by_service(): void
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

        \App\Models\ScanResult::factory()->forService('iam')->count(4)->create(['scan_id' => $scan->id]);
        \App\Models\ScanResult::factory()->forService('s3')->count(2)->create(['scan_id' => $scan->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/scans/{$scan->id}/results?service=iam");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(4, $data['total']);
    }

    /**
     * Test filtering scan results by status
     */
    public function test_can_filter_scan_results_by_status(): void
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

        \App\Models\ScanResult::factory()->count(3)->create(['scan_id' => $scan->id, 'status' => 'open']);
        \App\Models\ScanResult::factory()->count(2)->create(['scan_id' => $scan->id, 'status' => 'resolved']);

        $response = $this->actingAs($user)
            ->getJson("/api/scans/{$scan->id}/results?status=open");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(3, $data['total']);
    }

    /**
     * Test cancelling a pending scan
     */
    public function test_can_cancel_pending_scan(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->pending()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/scans/{$scan->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $scan->id,
                'status' => 'cancelled',
            ]);

        $scan->refresh();
        $this->assertEquals('cancelled', $scan->status);
        $this->assertNotNull($scan->completed_at);
    }

    /**
     * Test cancelling a running scan
     */
    public function test_can_cancel_running_scan(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/scans/{$scan->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'cancelled',
            ]);
    }

    /**
     * Test cannot cancel completed scan
     */
    public function test_cannot_cancel_completed_scan(): void
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

        $response = $this->actingAs($user)
            ->postJson("/api/scans/{$scan->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    /**
     * Test cannot cancel failed scan
     */
    public function test_cannot_cancel_failed_scan(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->failed()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/scans/{$scan->id}/cancel");

        $response->assertStatus(422);
    }

    /**
     * Test cannot cancel scan from another user's organization
     */
    public function test_cannot_cancel_scan_from_other_organization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->pending()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/scans/{$scan->id}/cancel");

        $response->assertStatus(404);
    }

    /**
     * Test scan results pagination
     */
    public function test_scan_results_pagination(): void
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

        \App\Models\ScanResult::factory()->count(10)->create(['scan_id' => $scan->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/scans/{$scan->id}/results?limit=5&offset=0");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(10, $data['total']);
        $this->assertEquals(5, $data['limit']);
        $this->assertEquals(0, $data['offset']);
        $this->assertCount(5, $data['findings']);
    }

    /**
     * Test scans list pagination
     */
    public function test_scans_list_pagination(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        Scan::factory()->count(15)->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/scans?limit=10&offset=0");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(15, $data['total']);
        $this->assertEquals(10, $data['limit']);
        $this->assertCount(10, $data['scans']);
    }

    /**
     * Test getting scan types endpoint
     */
    public function test_can_get_scan_types(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/scan-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'scanTypes' => [
                    '*' => [
                        'value',
                        'label',
                        'region',
                    ],
                ],
            ]);

        $data = $response->json();
        $values = array_column($data['scanTypes'], 'value');
        $this->assertContains('ec2', $values);
        $this->assertContains('iam', $values);
        $this->assertContains('s3', $values);
        $this->assertContains('rds', $values);
    }

    /**
     * Test unauthenticated user cannot access scans
     */
    public function test_unauthenticated_user_cannot_access_scans(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->getJson("/api/organizations/{$organization->org_id}/scans");

        $response->assertStatus(401);
    }

    /**
     * Test unauthenticated user cannot create scan
     */
    public function test_unauthenticated_user_cannot_create_scan(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->postJson("/api/organizations/{$organization->org_id}/scans", [
            'aws_account_id' => 'some-id',
            'scan_types' => ['iam'],
        ]);

        $response->assertStatus(401);
    }
}
