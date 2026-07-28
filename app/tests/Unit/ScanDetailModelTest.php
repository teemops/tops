<?php

namespace Tests\Unit;

use App\Models\Scan;
use App\Models\ScanDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanDetailModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_data_round_trips_as_an_array(): void
    {
        $raw = [
            'Users' => [
                ['UserName' => 'alice', 'Arn' => 'arn:aws:iam::123456789012:user/alice'],
            ],
        ];

        $detail = ScanDetail::factory()->create(['raw_data' => $raw]);

        $this->assertEquals($raw, $detail->fresh()->raw_data);
    }

    public function test_it_uses_a_uuid_primary_key(): void
    {
        $detail = ScanDetail::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $detail->id
        );
    }

    public function test_it_belongs_to_a_scan(): void
    {
        $scan = Scan::factory()->create();
        $detail = ScanDetail::factory()->create(['scan_id' => $scan->id]);

        $this->assertTrue($detail->scan->is($scan));
    }

    public function test_a_global_service_detail_has_no_region(): void
    {
        $detail = ScanDetail::factory()->iam()->create();

        $this->assertEquals('iam', $detail->service);
        $this->assertNull($detail->region);
    }

    public function test_a_regional_detail_records_its_region(): void
    {
        $detail = ScanDetail::factory()->ec2('ap-southeast-2')->create();

        $this->assertEquals('ec2', $detail->service);
        $this->assertEquals('ap-southeast-2', $detail->region);
    }

    /**
     * Action results are linked back to the resource that produced them.
     */
    public function test_a_detail_can_reference_a_parent_resource(): void
    {
        $scan = Scan::factory()->create();

        $detail = ScanDetail::factory()->create([
            'scan_id' => $scan->id,
            'resource_id' => 'alice',
            'parent_resource_id' => 'alice',
            'api_method' => 'listMFADevices',
        ]);

        $this->assertEquals('alice', $detail->fresh()->parent_resource_id);
    }

    /**
     * Failed API calls are persisted with the __error__ marker the rules engine
     * treats as "no configuration present".
     */
    public function test_an_error_marker_is_persisted_as_raw_data(): void
    {
        $detail = ScanDetail::factory()->create([
            'raw_data' => ['__error__' => true, 'error_message' => 'NoSuchPublicAccessBlockConfiguration'],
        ]);

        $this->assertTrue($detail->fresh()->raw_data['__error__']);
    }
}
