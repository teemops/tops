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
}
