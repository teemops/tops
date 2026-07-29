<?php

namespace Tests\Unit;

use App\Services\ServiceRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * ServiceRegistry is the single place that decides which services exist. These tests
 * pin the two things that matter: it reads the real tasks.json files correctly, and a
 * malformed contribution degrades to "that one service is missing" rather than taking
 * the whole registry (and with it the scan-types endpoint) down.
 */
class ServiceRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceRegistry::flush();
    }

    protected function tearDown(): void
    {
        ServiceRegistry::flush();
        parent::tearDown();
    }

    public function test_it_discovers_the_services_defined_under_rules_tasks(): void
    {
        $names = ServiceRegistry::names();

        foreach (['s3', 'iam', 'ec2', 'rds', 'cloudtrail', 'lambda', 'kms'] as $service) {
            $this->assertContains($service, $names);
        }
    }

    public function test_it_reads_the_declared_region_behaviour(): void
    {
        // These were hardcoded in ScanTypesService before the registry; the values must
        // not have drifted in the move, or scans silently change shape.
        $this->assertTrue(ServiceRegistry::get('ec2')['regional']);
        $this->assertTrue(ServiceRegistry::get('kms')['regional']);
        $this->assertFalse(ServiceRegistry::get('iam')['regional']);
        $this->assertFalse(ServiceRegistry::get('s3')['regional']);
    }

    public function test_it_defaults_client_to_the_service_name_and_exposes_labels(): void
    {
        $this->assertSame('cloudtrail', ServiceRegistry::get('cloudtrail')['client']);
        $this->assertSame('CloudTrail', ServiceRegistry::get('cloudtrail')['label']);
        $this->assertSame('KMS', ServiceRegistry::get('kms')['label']);
    }

    public function test_only_services_needing_bespoke_behaviour_declare_a_scanner(): void
    {
        $this->assertNotNull(ServiceRegistry::get('s3')['scanner']);
        $this->assertNotNull(ServiceRegistry::get('iam')['scanner']);
        $this->assertNull(ServiceRegistry::get('kms')['scanner']);
        $this->assertNull(ServiceRegistry::get('ec2')['scanner']);
    }

    public function test_profile_membership_is_read_from_the_service_definitions(): void
    {
        $basic = ServiceRegistry::namesForProfile('basic');

        $this->assertContains('s3', $basic);
        $this->assertContains('kms', $basic);
        $this->assertEmpty(ServiceRegistry::namesForProfile('no-such-profile'));
    }

    public function test_has_and_get_reject_an_unknown_service(): void
    {
        $this->assertFalse(ServiceRegistry::has('quantumdb'));
        $this->assertNull(ServiceRegistry::get('quantumdb'));
    }

    public function test_a_malformed_tasks_file_is_skipped_without_losing_the_others(): void
    {
        $directory = base_path('rules/tasks/brokenfixture');
        File::makeDirectory($directory, 0755, true);
        File::put($directory . '/tasks.json', '{ this is not json');

        try {
            Log::shouldReceive('error')->atLeast()->once();
            ServiceRegistry::flush();

            $names = ServiceRegistry::names();

            $this->assertNotContains('brokenfixture', $names);
            $this->assertContains('s3', $names, 'One bad file must not empty the registry');
        } finally {
            File::deleteDirectory($directory);
            ServiceRegistry::flush();
        }
    }

    public function test_a_service_whose_config_disagrees_with_its_directory_is_skipped(): void
    {
        // config.service is the identity used for scan_details.service and rule matching.
        // If it disagreed with the directory we would collect data under one name and
        // evaluate rules under another, producing no findings and no error.
        $directory = base_path('rules/tasks/mismatchfixture');
        File::makeDirectory($directory, 0755, true);
        File::put($directory . '/tasks.json', json_encode([
            'config' => ['service' => 'somethingelse', 'start' => 'listThings'],
        ]));

        try {
            Log::shouldReceive('error')->atLeast()->once();
            ServiceRegistry::flush();

            $names = ServiceRegistry::names();

            $this->assertNotContains('mismatchfixture', $names);
            $this->assertNotContains('somethingelse', $names);
        } finally {
            File::deleteDirectory($directory);
            ServiceRegistry::flush();
        }
    }
}
