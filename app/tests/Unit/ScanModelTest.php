<?php

namespace Tests\Unit;

use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that scan_types is cast to array
     */
    public function test_scan_types_is_cast_to_array(): void
    {
        $scan = Scan::factory()->create([
            'scan_types' => ['iam', 's3', 'ec2'],
        ]);

        $this->assertIsArray($scan->scan_types);
        $this->assertEquals(['iam', 's3', 'ec2'], $scan->scan_types);
    }

    /**
     * Test that scan_types can be stored as JSON and retrieved as array
     */
    public function test_scan_types_stored_as_json_retrieved_as_array(): void
    {
        $scan = Scan::factory()->create([
            'scan_types' => ['ec2'],
        ]);

        // Refresh from database
        $scan->refresh();

        $this->assertIsArray($scan->scan_types);
        $this->assertEquals(['ec2'], $scan->scan_types);
    }

    /**
     * Test hasScanType method returns true when type exists
     */
    public function test_has_scan_type_returns_true_when_type_exists(): void
    {
        $scan = Scan::factory()->create([
            'scan_types' => ['iam', 's3'],
        ]);

        $this->assertTrue($scan->hasScanType('iam'));
        $this->assertTrue($scan->hasScanType('s3'));
    }

    /**
     * Test hasScanType method returns false when type does not exist
     */
    public function test_has_scan_type_returns_false_when_type_not_exists(): void
    {
        $scan = Scan::factory()->create([
            'scan_types' => ['iam', 's3'],
        ]);

        $this->assertFalse($scan->hasScanType('ec2'));
    }

    /**
     * Test hasScanType handles null scan_types
     */
    public function test_has_scan_type_handles_null_scan_types(): void
    {
        $scan = Scan::factory()->create([
            'scan_types' => null,
        ]);

        $this->assertFalse($scan->hasScanType('iam'));
    }

    /**
     * Test scan_types can be updated
     */
    public function test_scan_types_can_be_updated(): void
    {
        $scan = Scan::factory()->create([
            'scan_types' => ['iam'],
        ]);

        $scan->update([
            'scan_types' => ['ec2', 's3'],
        ]);

        $scan->refresh();
        $this->assertEquals(['ec2', 's3'], $scan->scan_types);
    }

    /**
     * Test scan belongs to organization
     */
    public function test_scan_belongs_to_organization(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $awsAccount = \App\Models\AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $this->assertInstanceOf(\App\Models\Organization::class, $scan->organization);
        $this->assertEquals($organization->id, $scan->organization->id);
    }

    /**
     * Test scan belongs to AWS account
     */
    public function test_scan_belongs_to_aws_account(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $awsAccount = \App\Models\AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $this->assertInstanceOf(\App\Models\AwsAccount::class, $scan->awsAccount);
        $this->assertEquals($awsAccount->id, $scan->awsAccount->id);
    }

    /**
     * Test scan has many results
     */
    public function test_scan_has_many_results(): void
    {
        $scan = Scan::factory()->create();
        \App\Models\ScanResult::factory()->count(3)->create([
            'scan_id' => $scan->id,
        ]);

        $this->assertCount(3, $scan->results);
        $this->assertInstanceOf(\App\Models\ScanResult::class, $scan->results->first());
    }

    /**
     * Test scan has many details
     */
    public function test_scan_has_many_details(): void
    {
        $scan = Scan::factory()->create();
        \App\Models\ScanDetail::factory()->count(2)->create([
            'scan_id' => $scan->id,
        ]);

        $this->assertCount(2, $scan->details);
        $this->assertInstanceOf(\App\Models\ScanDetail::class, $scan->details->first());
    }

    /**
     * Test started_at is cast to datetime
     */
    public function test_started_at_is_cast_to_datetime(): void
    {
        $scan = Scan::factory()->running()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $scan->started_at);
    }

    /**
     * Test completed_at is cast to datetime
     */
    public function test_completed_at_is_cast_to_datetime(): void
    {
        $scan = Scan::factory()->completed()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $scan->completed_at);
    }

    /**
     * Test scan uses UUIDs
     */
    public function test_scan_uses_uuids(): void
    {
        $scan = Scan::factory()->create();

        // UUID format: 8-4-4-4-12 hex characters
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $scan->id
        );
    }

    /**
     * Test scan factory states
     */
    public function test_scan_factory_pending_state(): void
    {
        $scan = Scan::factory()->pending()->create();
        $this->assertEquals('pending', $scan->status);
    }

    /**
     * Test scan factory running state
     */
    public function test_scan_factory_running_state(): void
    {
        $scan = Scan::factory()->running()->create();
        $this->assertEquals('running', $scan->status);
        $this->assertNotNull($scan->started_at);
    }

    /**
     * Test scan factory completed state
     */
    public function test_scan_factory_completed_state(): void
    {
        $scan = Scan::factory()->completed()->create();
        $this->assertEquals('completed', $scan->status);
        $this->assertNotNull($scan->started_at);
        $this->assertNotNull($scan->completed_at);
    }

    /**
     * Test scan factory failed state
     */
    public function test_scan_factory_failed_state(): void
    {
        $scan = Scan::factory()->failed()->create();
        $this->assertEquals('failed', $scan->status);
        $this->assertNotNull($scan->error_message);
    }

    /**
     * Test scan factory cancelled state
     */
    public function test_scan_factory_cancelled_state(): void
    {
        $scan = Scan::factory()->cancelled()->create();
        $this->assertEquals('cancelled', $scan->status);
        $this->assertNotNull($scan->completed_at);
    }

    /**
     * Settling is what completes a region-based scan now. A scan already in a terminal
     * state must not be settled again — that would re-run findings evaluation and could
     * overwrite an accurate result with a fallback one.
     */
    public function test_settling_does_nothing_for_a_scan_already_terminal(): void
    {
        $scan = Scan::factory()->create([
            'scan_types' => ['ec2'],
            'status' => 'cancelled',
        ]);

        $this->assertFalse($scan->settleRegionScan(true, 'stale sweep'));

        $scan->refresh();
        $this->assertEquals('cancelled', $scan->status);
    }

    /**
     * A scan whose orchestrator died before flipping it to 'running' still has region
     * jobs reporting in, so 'pending' has to be settleable or nothing ever completes it.
     */
    public function test_a_pending_scan_can_still_be_settled(): void
    {
        $scan = Scan::factory()->pending()->create([
            'scan_types' => ['ec2'],
        ]);

        $this->assertTrue($scan->settleRegionScan(true, 'orphaned batch'));

        $scan->refresh();
        $this->assertEquals('completed', $scan->status);
        $this->assertTrue($scan->is_partial);
    }

    public function test_settling_records_completion_time(): void
    {
        $scan = Scan::factory()->create([
            'scan_types' => ['ec2'],
            'status' => 'running',
            'started_at' => now()->subMinutes(5),
        ]);

        $scan->settleRegionScan(false);

        $scan->refresh();
        $this->assertEquals('completed', $scan->status);
        $this->assertNotNull($scan->completed_at);
    }
}
