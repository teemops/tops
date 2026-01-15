<?php

namespace Tests\Unit;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanResultModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a scan result belongs to a scan
     */
    public function test_scan_result_belongs_to_scan(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        $scanResult = ScanResult::factory()->create([
            'scan_id' => $scan->id,
        ]);

        $this->assertInstanceOf(Scan::class, $scanResult->scan);
        $this->assertEquals($scan->id, $scanResult->scan->id);
    }

    /**
     * Test scan result can be created with all fields
     */
    public function test_scan_result_can_be_created_with_all_fields(): void
    {
        $scan = Scan::factory()->create();

        $scanResult = ScanResult::create([
            'scan_id' => $scan->id,
            'severity' => 'critical',
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-123',
            'finding_type' => 'unused_credentials',
            'title' => 'Unused IAM User Credentials',
            'description' => 'IAM user has credentials that have not been used in 90 days',
            'remediation' => 'Remove or rotate the unused credentials',
            'status' => 'open',
        ]);

        $this->assertEquals('critical', $scanResult->severity);
        $this->assertEquals('iam', $scanResult->service);
        $this->assertEquals('user', $scanResult->resource_type);
        $this->assertEquals('test-user-123', $scanResult->resource_id);
        $this->assertEquals('unused_credentials', $scanResult->finding_type);
        $this->assertEquals('Unused IAM User Credentials', $scanResult->title);
        $this->assertEquals('open', $scanResult->status);
    }

    /**
     * Test scan result severity values
     */
    public function test_scan_result_can_have_different_severities(): void
    {
        $scan = Scan::factory()->create();

        $critical = ScanResult::factory()->critical()->create(['scan_id' => $scan->id]);
        $high = ScanResult::factory()->high()->create(['scan_id' => $scan->id]);
        $medium = ScanResult::factory()->create(['scan_id' => $scan->id, 'severity' => 'medium']);
        $low = ScanResult::factory()->create(['scan_id' => $scan->id, 'severity' => 'low']);

        $this->assertEquals('critical', $critical->severity);
        $this->assertEquals('high', $high->severity);
        $this->assertEquals('medium', $medium->severity);
        $this->assertEquals('low', $low->severity);
    }

    /**
     * Test scan result for specific service
     */
    public function test_scan_result_for_service_factory_state(): void
    {
        $scan = Scan::factory()->create();

        $iamResult = ScanResult::factory()->forService('iam')->create(['scan_id' => $scan->id]);
        $s3Result = ScanResult::factory()->forService('s3')->create(['scan_id' => $scan->id]);
        $ec2Result = ScanResult::factory()->forService('ec2')->create(['scan_id' => $scan->id]);

        $this->assertEquals('iam', $iamResult->service);
        $this->assertEquals('s3', $s3Result->service);
        $this->assertEquals('ec2', $ec2Result->service);
    }

    /**
     * Test resolved_at is cast to datetime
     */
    public function test_resolved_at_is_cast_to_datetime(): void
    {
        $scan = Scan::factory()->create();
        $resolvedAt = now();

        $scanResult = ScanResult::factory()->create([
            'scan_id' => $scan->id,
            'status' => 'resolved',
            'resolved_at' => $resolvedAt,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $scanResult->resolved_at);
    }

    /**
     * Test a scan can have multiple results
     */
    public function test_scan_can_have_multiple_results(): void
    {
        $scan = Scan::factory()->create();

        ScanResult::factory()->count(5)->create(['scan_id' => $scan->id]);

        $this->assertCount(5, $scan->results);
    }

    /**
     * Test scan results can be filtered by severity
     */
    public function test_scan_results_can_be_filtered_by_severity(): void
    {
        $scan = Scan::factory()->create();

        ScanResult::factory()->count(3)->critical()->create(['scan_id' => $scan->id]);
        ScanResult::factory()->count(2)->high()->create(['scan_id' => $scan->id]);

        $criticalResults = ScanResult::where('scan_id', $scan->id)
            ->where('severity', 'critical')
            ->get();

        $highResults = ScanResult::where('scan_id', $scan->id)
            ->where('severity', 'high')
            ->get();

        $this->assertCount(3, $criticalResults);
        $this->assertCount(2, $highResults);
    }

    /**
     * Test scan results can be filtered by service
     */
    public function test_scan_results_can_be_filtered_by_service(): void
    {
        $scan = Scan::factory()->create();

        ScanResult::factory()->count(4)->forService('iam')->create(['scan_id' => $scan->id]);
        ScanResult::factory()->count(2)->forService('s3')->create(['scan_id' => $scan->id]);

        $iamResults = ScanResult::where('scan_id', $scan->id)
            ->where('service', 'iam')
            ->get();

        $s3Results = ScanResult::where('scan_id', $scan->id)
            ->where('service', 's3')
            ->get();

        $this->assertCount(4, $iamResults);
        $this->assertCount(2, $s3Results);
    }

    /**
     * Test scan result uses UUIDs
     */
    public function test_scan_result_uses_uuids(): void
    {
        $scanResult = ScanResult::factory()->create();

        // UUID format: 8-4-4-4-12 hex characters
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $scanResult->id
        );
    }

    /**
     * Test scan result status can be updated
     */
    public function test_scan_result_status_can_be_updated(): void
    {
        $scanResult = ScanResult::factory()->create(['status' => 'open']);

        $this->assertEquals('open', $scanResult->status);

        $scanResult->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $scanResult->refresh();
        $this->assertEquals('resolved', $scanResult->status);
        $this->assertNotNull($scanResult->resolved_at);
    }
}
