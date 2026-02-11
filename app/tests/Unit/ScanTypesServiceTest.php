<?php

namespace Tests\Unit;

use App\Services\ScanTypesService;
use Tests\TestCase;

class ScanTypesServiceTest extends TestCase
{
    /**
     * Test getAll returns array of scan type values
     */
    public function test_get_all_returns_scan_type_values(): void
    {
        $types = ScanTypesService::getAll();

        $this->assertIsArray($types);
        $this->assertContains('ec2', $types);
        $this->assertContains('iam', $types);
        $this->assertContains('s3', $types);
        $this->assertContains('rds', $types);
    }

    /**
     * Test getAllWithLabels returns array with value and label keys
     */
    public function test_get_all_with_labels_returns_value_and_label(): void
    {
        $types = ScanTypesService::getAllWithLabels();

        $this->assertIsArray($types);
        $this->assertNotEmpty($types);

        foreach ($types as $type) {
            $this->assertArrayHasKey('value', $type);
            $this->assertArrayHasKey('label', $type);
            $this->assertArrayHasKey('region', $type);
        }
    }

    /**
     * Test getAllWithLabels includes correct labels
     */
    public function test_get_all_with_labels_has_correct_labels(): void
    {
        $types = ScanTypesService::getAllWithLabels();

        $typeMap = [];
        foreach ($types as $type) {
            $typeMap[$type['value']] = $type['label'];
        }

        $this->assertEquals('EC2', $typeMap['ec2']);
        $this->assertEquals('IAM', $typeMap['iam']);
        $this->assertEquals('S3', $typeMap['s3']);
        $this->assertEquals('RDS', $typeMap['rds']);
    }

    /**
     * Test isValid returns true for valid scan types
     */
    public function test_is_valid_returns_true_for_valid_types(): void
    {
        $this->assertTrue(ScanTypesService::isValid('ec2'));
        $this->assertTrue(ScanTypesService::isValid('iam'));
        $this->assertTrue(ScanTypesService::isValid('s3'));
        $this->assertTrue(ScanTypesService::isValid('rds'));
    }

    /**
     * Test isValid returns false for invalid scan types
     */
    public function test_is_valid_returns_false_for_invalid_types(): void
    {
        $this->assertFalse(ScanTypesService::isValid('invalid'));
        $this->assertFalse(ScanTypesService::isValid(''));
        $this->assertFalse(ScanTypesService::isValid('lambda'));
        $this->assertFalse(ScanTypesService::isValid('EC2')); // Case-sensitive
    }

    /**
     * Test getValidationRule returns proper Laravel validation rule
     */
    public function test_get_validation_rule_returns_in_rule(): void
    {
        $rule = ScanTypesService::getValidationRule();

        $this->assertIsString($rule);
        $this->assertStringStartsWith('in:', $rule);
        $this->assertStringContainsString('ec2', $rule);
        $this->assertStringContainsString('iam', $rule);
        $this->assertStringContainsString('s3', $rule);
        $this->assertStringContainsString('rds', $rule);
    }

    /**
     * Test getRegionBased returns only region-based scan types
     */
    public function test_get_region_based_returns_region_based_types(): void
    {
        $regionBased = ScanTypesService::getRegionBased();

        $this->assertIsArray($regionBased);
        $this->assertContains('ec2', $regionBased);
        $this->assertContains('rds', $regionBased);
        $this->assertNotContains('iam', $regionBased); // IAM is global
        $this->assertNotContains('s3', $regionBased);   // S3 bucket list is global
    }

    /**
     * Test getNonRegionBased returns only non-region-based scan types
     */
    public function test_get_non_region_based_returns_global_types(): void
    {
        $nonRegionBased = ScanTypesService::getNonRegionBased();

        $this->assertIsArray($nonRegionBased);
        $this->assertContains('iam', $nonRegionBased);
        $this->assertContains('s3', $nonRegionBased);  // S3 bucket list is global
        $this->assertNotContains('ec2', $nonRegionBased);
        $this->assertNotContains('rds', $nonRegionBased);
    }

    /**
     * Test isRegionBased returns true for region-based types (EC2, RDS only)
     */
    public function test_is_region_based_returns_true_for_region_types(): void
    {
        $this->assertTrue(ScanTypesService::isRegionBased('ec2'));
        $this->assertTrue(ScanTypesService::isRegionBased('rds'));
    }

    /**
     * Test isRegionBased returns false for global/single-region types (IAM, S3)
     */
    public function test_is_region_based_returns_false_for_global_types(): void
    {
        $this->assertFalse(ScanTypesService::isRegionBased('iam'));
        $this->assertFalse(ScanTypesService::isRegionBased('s3'));
    }

    /**
     * Test isRegionBased returns false for invalid types
     */
    public function test_is_region_based_returns_false_for_invalid_types(): void
    {
        $this->assertFalse(ScanTypesService::isRegionBased('invalid'));
        $this->assertFalse(ScanTypesService::isRegionBased(''));
    }

    /**
     * Test region-based and non-region-based types are mutually exclusive
     */
    public function test_region_and_non_region_types_are_mutually_exclusive(): void
    {
        $regionBased = ScanTypesService::getRegionBased();
        $nonRegionBased = ScanTypesService::getNonRegionBased();
        $all = ScanTypesService::getAll();

        // No overlap
        $this->assertEmpty(array_intersect($regionBased, $nonRegionBased));

        // Together they make up all types
        $combined = array_merge($regionBased, $nonRegionBased);
        sort($combined);
        sort($all);
        $this->assertEquals($all, $combined);
    }
}
