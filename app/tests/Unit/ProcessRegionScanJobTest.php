<?php

namespace Tests\Unit;

use App\Jobs\ProcessRegionScanJob;
use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanDetail;
use App\Models\ScanResult;
use App\Services\AwsSecurityScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ProcessRegionScanJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * A redelivered region job must not re-collect data it already has.
     *
     * SQS is at-least-once and this job retries three times, but scan_details has no
     * unique constraint — so before the guard, a redelivery silently doubled every row
     * for that region. The job still has to run to completion (it is what calls the
     * completion check), it just must not write again.
     */
    public function test_a_redelivered_job_does_not_recollect_data_for_its_region(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
            'rulesets' => ['basic'],
            'expected_regions_count' => 1,
        ]);

        // Stand in for what a first, successful delivery would have written.
        ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'ec2',
            'api_method' => 'describeInstances',
            'raw_data' => ['Reservations' => []],
            'region' => 'us-east-1',
        ]);

        $before = ScanDetail::where('scan_id', $scan->id)->count();

        // No AWS credentials are available here; the job completing at all proves it
        // never tried to assume a role or call an API.
        (new ProcessRegionScanJob($scan, 'us-east-1', 'ec2'))->handle();

        $this->assertSame($before, ScanDetail::where('scan_id', $scan->id)->count());
    }

    /**
     * The guard is scoped to one service in one region — a different pair is still
     * collected, or a multi-service scan would stop after its first region job.
     */
    public function test_the_guard_is_scoped_to_a_single_service_and_region(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2', 'kms'],
        ]);

        ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'ec2',
            'api_method' => 'describeInstances',
            'raw_data' => [],
            'region' => 'us-east-1',
        ]);

        $collected = function (string $service, string $region) use ($scan) {
            $job = new ProcessRegionScanJob($scan, $region, $service);
            $method = new \ReflectionMethod($job, 'hasAlreadyCollected');
            $method->setAccessible(true);

            return $method->invoke($job);
        };

        $this->assertTrue($collected('ec2', 'us-east-1'));
        $this->assertFalse($collected('ec2', 'eu-west-1'), 'A different region still needs collecting');
        $this->assertFalse($collected('kms', 'us-east-1'), 'A different service still needs collecting');
    }

    /**
     * The completion line has to carry every phase timing, because it is the only
     * per-job record of where a scan's time goes.
     *
     * A full scan fans out ~153 of these, so the baseline (PERF-1) is built by
     * aggregating this one line rather than one line per phase. If a key is dropped or
     * renamed, the aggregation silently reports zero for that phase instead of failing.
     */
    public function test_the_completion_log_carries_every_phase_timing(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
            'rulesets' => ['basic'],
            'expected_regions_count' => 1,
        ]);

        // Pre-collected, so the job runs end to end without needing AWS credentials.
        ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'ec2',
            'api_method' => 'describeInstances',
            'raw_data' => ['Reservations' => []],
            'region' => 'us-east-1',
        ]);

        $context = null;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$context) {
            if ($event->message === 'Region scan completed') {
                $context = $event->context;
            }
        });

        (new ProcessRegionScanJob($scan, 'us-east-1', 'ec2'))->handle();

        $this->assertNotNull($context, 'The job did not log a completion line');

        foreach (['assume_role_ms', 'collection_ms', 'findings_ms', 'completion_check_ms', 'total_ms'] as $key) {
            $this->assertArrayHasKey($key, $context, "Completion line is missing {$key}");
            $this->assertIsInt($context[$key], "{$key} must be an integer count of milliseconds");
        }
    }

    /**
     * Test that job skips processing when scan is cancelled
     */
    public function test_job_skips_when_scan_is_cancelled(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan = Scan::factory()->cancelled()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Scan cancelled, skipping region processing', [
                'scan_id' => $scan->id,
                'region' => 'us-east-1',
            ]);

        $job = new ProcessRegionScanJob($scan, 'us-east-1');
        $job->handle();

        $scan->refresh();
        $this->assertEquals('cancelled', $scan->status);
    }

    /**
     * Test processing EC2 scan for a specific region
     * Note: This test requires AWS credentials or mocking to pass.
     * Skipping in test environment to avoid actual AWS API calls.
     */
    public function test_processes_ec2_scan_for_region(): void
    {
        $this->markTestSkipped('Requires AWS credentials or proper mocking of AwsSecurityScanner. Integration test needed.');
        
        // This test would require dependency injection or service container binding
        // to properly mock AwsSecurityScanner, which is currently instantiated
        // directly in the job. For now, this is better tested via integration tests.
    }

    /**
     * Test job fails when AWS account is not completed
     */
    public function test_job_fails_when_aws_account_not_completed(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->pending()->create([
            'organization_id' => $organization->id,
        ]);

        $scan = Scan::factory()->running()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
        ]);

        $job = new ProcessRegionScanJob($scan, 'us-east-1');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AWS account is not active');

        $job->handle();
    }

    /**
     * Test job handles exceptions gracefully
     * Note: This test requires proper mocking of AwsSecurityScanner.
     * Skipping in test environment as scanner is instantiated directly in job.
     */
    public function test_job_handles_exceptions(): void
    {
        $this->markTestSkipped('Requires dependency injection to properly mock AwsSecurityScanner. Integration test needed.');
    }
}
