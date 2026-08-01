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
     * Test checkAndMarkRegionBasedScanComplete does nothing for non-running scan
     */
    public function test_check_and_mark_complete_does_nothing_for_non_running_scan(): void
    {
        $scan = Scan::factory()->pending()->create([
            'scan_types' => ['ec2'],
            'expected_regions_count' => 5,
        ]);

        // Add some scan details
        \App\Models\ScanDetail::factory()->ec2('us-east-1')->create(['scan_id' => $scan->id]);
        \App\Models\ScanDetail::factory()->ec2('us-west-2')->create(['scan_id' => $scan->id]);

        $scan->checkAndMarkRegionBasedScanComplete();

        $scan->refresh();
        $this->assertEquals('pending', $scan->status);
    }

    /**
     * Test checkAndMarkRegionBasedScanComplete does nothing for non-region-based scan types
     */
    public function test_check_and_mark_complete_does_nothing_for_non_region_based_types(): void
    {
        $scan = Scan::factory()->running()->create([
            'scan_types' => ['iam'], // IAM is not region-based
            'expected_regions_count' => 5,
        ]);

        $scan->checkAndMarkRegionBasedScanComplete();

        $scan->refresh();
        $this->assertEquals('running', $scan->status);
    }

    /**
     * Test checkAndMarkRegionBasedScanComplete marks scan as completed when all regions done
     */
    public function test_check_and_mark_complete_marks_scan_completed_when_all_regions_done(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $awsAccount = \App\Models\AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
            'expected_regions_count' => 3,
        ]);

        // Add scan details for all expected regions
        \App\Models\ScanDetail::factory()->ec2('us-east-1')->create(['scan_id' => $scan->id]);
        \App\Models\ScanDetail::factory()->ec2('us-west-2')->create(['scan_id' => $scan->id]);
        \App\Models\ScanDetail::factory()->ec2('eu-west-1')->create(['scan_id' => $scan->id]);

        $scan->checkAndMarkRegionBasedScanComplete();

        $scan->refresh();
        $this->assertEquals('completed', $scan->status);
        $this->assertNotNull($scan->completed_at);
    }

    /**
     * The completion line reports wall time for the whole scan — the headline number
     * the parallel-scan work (PERF-1) is measured against.
     */
    public function test_completion_log_reports_total_scan_wall_time(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $awsAccount = \App\Models\AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
            'expected_regions_count' => 1,
            'started_at' => now()->subSeconds(90),
        ]);

        \App\Models\ScanDetail::factory()->ec2('us-east-1')->create(['scan_id' => $scan->id]);

        $context = null;
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Log\Events\MessageLogged::class,
            function ($event) use (&$context) {
                if ($event->message === 'Region-based scan marked as completed') {
                    $context = $event->context;
                }
            }
        );

        $scan->checkAndMarkRegionBasedScanComplete();

        $this->assertNotNull($context, 'The scan did not log a completion line');
        $this->assertArrayHasKey('total_ms', $context);
        $this->assertGreaterThanOrEqual(
            90_000,
            $context['total_ms'],
            'total_ms must measure from started_at, not from when the check ran'
        );
    }

    /**
     * Test checkAndMarkRegionBasedScanComplete does not mark complete when not enough regions
     */
    public function test_check_and_mark_complete_does_not_mark_complete_when_not_enough_regions(): void
    {
        $scan = Scan::factory()->running()->create([
            'scan_types' => ['ec2'],
            'expected_regions_count' => 5,
        ]);

        // Add scan details for only 2 regions (less than expected 5)
        \App\Models\ScanDetail::factory()->ec2('us-east-1')->create(['scan_id' => $scan->id]);
        \App\Models\ScanDetail::factory()->ec2('us-west-2')->create(['scan_id' => $scan->id]);

        $scan->checkAndMarkRegionBasedScanComplete();

        $scan->refresh();
        $this->assertEquals('running', $scan->status);
        $this->assertNull($scan->completed_at);
    }

    /**
     * Test checkAndMarkRegionBasedScanComplete updates AWS account last_scan_at
     */
    public function test_check_and_mark_complete_updates_aws_account_last_scan_at(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $awsAccount = \App\Models\AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
            'last_scan_at' => null,
        ]);
        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
            'expected_regions_count' => 1,
        ]);

        \App\Models\ScanDetail::factory()->ec2('us-east-1')->create(['scan_id' => $scan->id]);

        $this->assertNull($awsAccount->last_scan_at);

        $scan->checkAndMarkRegionBasedScanComplete();

        $awsAccount->refresh();
        $this->assertNotNull($awsAccount->last_scan_at);
    }

    /**
     * Test checkAndMarkRegionBasedScanComplete counts unique regions correctly
     */
    public function test_check_and_mark_complete_counts_unique_regions(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $awsAccount = \App\Models\AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
            'expected_regions_count' => 2,
        ]);

        // Add multiple scan details for the same region (should count as 1)
        \App\Models\ScanDetail::factory()->ec2('us-east-1')->create(['scan_id' => $scan->id]);
        \App\Models\ScanDetail::factory()->ec2('us-east-1')->create(['scan_id' => $scan->id]);
        \App\Models\ScanDetail::factory()->ec2('us-west-2')->create(['scan_id' => $scan->id]);

        $scan->checkAndMarkRegionBasedScanComplete();

        $scan->refresh();
        $this->assertEquals('completed', $scan->status);
    }
}
