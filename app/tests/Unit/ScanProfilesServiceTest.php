<?php

namespace Tests\Unit;

use App\Services\ScanProfilesService;
use Tests\TestCase;

class ScanProfilesServiceTest extends TestCase
{
    /**
     * A profile is available once its ruleset has rules. basic.json and cis.json
     * are populated; pci.json is still empty, so PCI stays hidden.
     */
    public function test_only_profiles_with_rules_are_available(): void
    {
        $available = ScanProfilesService::getAvailable();

        $this->assertContains('basic', $available);
        $this->assertContains('cis', $available);
        $this->assertNotContains('pci', $available);

        $this->assertTrue(ScanProfilesService::isAvailable('basic'));
        $this->assertTrue(ScanProfilesService::isAvailable('cis'));
        $this->assertFalse(ScanProfilesService::isAvailable('pci'));
        $this->assertFalse(ScanProfilesService::isAvailable('nonexistent'));
    }

    public function test_available_profiles_carry_labels_and_services(): void
    {
        $profiles = ScanProfilesService::getAvailableWithLabels();

        $this->assertNotEmpty($profiles);
        foreach ($profiles as $profile) {
            $this->assertArrayHasKey('value', $profile);
            $this->assertArrayHasKey('label', $profile);
            $this->assertArrayHasKey('description', $profile);
            $this->assertArrayHasKey('services', $profile);
        }
    }

    public function test_validation_rule_only_allows_available_profiles(): void
    {
        $rule = ScanProfilesService::getValidationRule();

        $this->assertStringStartsWith('in:', $rule);
        $this->assertStringContainsString('basic', $rule);
        $this->assertStringContainsString('cis', $rule);
        $this->assertStringNotContainsString('pci', $rule);
    }

    public function test_basic_expands_to_all_seven_services_and_basic_ruleset(): void
    {
        $this->assertSame(
            ['s3', 'iam', 'ec2', 'rds', 'cloudtrail', 'lambda', 'kms'],
            ScanProfilesService::servicesFor(['basic'])
        );
        $this->assertSame(['basic'], ScanProfilesService::rulesetsFor(['basic']));
    }

    public function test_multiple_profiles_union_services_and_rulesets_without_duplicates(): void
    {
        // basic + cis: services overlap fully (deduped), rulesets combine
        $services = ScanProfilesService::servicesFor(['basic', 'cis']);
        $rulesets = ScanProfilesService::rulesetsFor(['basic', 'cis']);

        $this->assertSame($services, array_values(array_unique($services)));
        $this->assertContains('s3', $services);
        $this->assertEqualsCanonicalizing(['basic', 'cis'], $rulesets);
    }
}
