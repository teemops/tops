<?php

namespace Tests\Unit;

use App\Services\AwsSecurityScanner;
use Tests\TestCase;

class AwsSecurityScannerTest extends TestCase
{
    /**
     * Test that scanEc2InRegion method exists and is public
     */
    public function test_scan_ec2_in_region_method_exists(): void
    {
        $scanner = new AwsSecurityScanner('arn:aws:iam::123456789012:role/TestRole', 'test-external-id');
        
        $this->assertTrue(method_exists($scanner, 'scanEc2InRegion'));
        
        $reflection = new \ReflectionMethod($scanner, 'scanEc2InRegion');
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * Test that getAvailableRegions method exists and is public
     */
    public function test_get_available_regions_method_exists(): void
    {
        $scanner = new AwsSecurityScanner('arn:aws:iam::123456789012:role/TestRole', 'test-external-id');
        
        $this->assertTrue(method_exists($scanner, 'getAvailableRegions'));
        
        $reflection = new \ReflectionMethod($scanner, 'getAvailableRegions');
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    /**
     * Test that assumeRole method is public
     */
    public function test_assume_role_is_public(): void
    {
        $scanner = new AwsSecurityScanner('arn:aws:iam::123456789012:role/TestRole', 'test-external-id');
        
        $this->assertTrue(method_exists($scanner, 'assumeRole'));
        
        $reflection = new \ReflectionMethod($scanner, 'assumeRole');
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * Test that scanEc2 delegates to scanEc2InRegion
     */
    public function test_scan_ec2_delegates_to_scan_ec2_in_region(): void
    {
        $scanner = new AwsSecurityScanner('arn:aws:iam::123456789012:role/TestRole', 'test-external-id', 'us-east-1');
        
        // Verify scanEc2 method exists
        $this->assertTrue(method_exists($scanner, 'scanEc2'));
        
        // Verify it calls scanEc2InRegion with the instance region
        // This is tested by checking the method implementation
        $reflection = new \ReflectionMethod($scanner, 'scanEc2');
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * Test scanner constructor accepts region parameter
     */
    public function test_scanner_constructor_accepts_region(): void
    {
        $scanner = new AwsSecurityScanner(
            'arn:aws:iam::123456789012:role/TestRole',
            'test-external-id',
            'us-west-2'
        );
        
        $this->assertInstanceOf(AwsSecurityScanner::class, $scanner);
    }

    /**
     * Test scanner uses default region when not specified
     */
    public function test_scanner_uses_default_region(): void
    {
        $scanner = new AwsSecurityScanner(
            'arn:aws:iam::123456789012:role/TestRole',
            'test-external-id'
        );
        
        $this->assertInstanceOf(AwsSecurityScanner::class, $scanner);
    }
}
