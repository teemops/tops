<?php

namespace Tests\Unit;

use App\Jobs\ProcessAuditScanJob;
use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Services\RegionResourceIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Region pruning must only ever remove work on positive evidence. GetResources reports
 * "tagged or previously tagged" resources, so an absent service can mean "no resources"
 * or "resources nobody tagged" — and acting on the second reports a region as clean that
 * was never scanned. Every uncertain path here has to dispatch.
 */
class RegionResourceIndexTest extends TestCase
{
    use RefreshDatabase;

    private function scan(array $scanTypes = ['ec2']): Scan
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        return Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => $scanTypes,
        ]);
    }

    /**
     * @param string[]|null $prefixes What the index reports for the region
     */
    private function canSkip(string $service, ?array $prefixes): bool
    {
        $job = new ProcessAuditScanJob($this->scan());

        // Seed the memoised index so no AWS call is attempted.
        $property = new \ReflectionProperty($job, 'regionServicePrefixes');
        $property->setAccessible(true);
        $property->setValue($job, ['us-east-1' => $prefixes]);

        $method = new \ReflectionMethod($job, 'canSkipRegion');
        $method->setAccessible(true);

        return $method->invoke($job, $service, 'us-east-1', []);
    }

    public function test_a_service_absent_from_an_indexed_region_is_skipped(): void
    {
        Config::set('scan.prune_regions_with_tagging', true);

        $this->assertTrue($this->canSkip('ec2', ['s3', 'lambda']));
    }

    public function test_a_service_present_in_the_region_is_dispatched(): void
    {
        Config::set('scan.prune_regions_with_tagging', true);

        $this->assertFalse($this->canSkip('ec2', ['ec2', 'lambda']));
    }

    /**
     * The failure mode that matters: an unindexable region must never be pruned.
     */
    public function test_an_unindexable_region_is_never_skipped(): void
    {
        Config::set('scan.prune_regions_with_tagging', true);

        $this->assertFalse($this->canSkip('ec2', null));
    }

    public function test_nothing_is_skipped_while_the_feature_is_off(): void
    {
        Config::set('scan.prune_regions_with_tagging', false);

        $this->assertFalse($this->canSkip('ec2', ['s3']));
    }

    /**
     * A service whose ARNs do not carry its own name must be matched on its declared
     * arnService, or it would look absent in every region and never be scanned.
     */
    public function test_matching_uses_the_declared_arn_service(): void
    {
        Config::set('scan.prune_regions_with_tagging', true);

        if (!\App\Services\ServiceRegistry::has('elbv2')) {
            $this->markTestSkipped('elbv2 is not registered');
        }

        $this->assertFalse($this->canSkip('elbv2', ['elasticloadbalancing']));
        $this->assertTrue($this->canSkip('elbv2', ['ec2']));
    }

    public function test_service_prefixes_are_parsed_out_of_arns(): void
    {
        $index = new RegionResourceIndex();
        $method = new \ReflectionMethod($index, 'servicePrefixOf');
        $method->setAccessible(true);

        $this->assertSame('ec2', $method->invoke($index, 'arn:aws:ec2:us-east-1:123456789012:instance/i-1'));
        $this->assertSame(
            'elasticloadbalancing',
            $method->invoke($index, 'arn:aws:elasticloadbalancing:us-east-1:123456789012:loadbalancer/app/x/1')
        );
        $this->assertSame('s3', $method->invoke($index, 'arn:aws:s3:::my-bucket'));
        $this->assertNull($method->invoke($index, 'not-an-arn'));
        $this->assertNull($method->invoke($index, ''));
    }
}
