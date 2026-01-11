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
     * Test validation requires scan_types
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
            ->assertJsonValidationErrors(['scan_types']);
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
            ->assertJsonValidationErrors(['scan_types']);
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
}
