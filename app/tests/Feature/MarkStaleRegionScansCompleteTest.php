<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkStaleRegionScansCompleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A region-based scan that started long enough ago to be considered stuck.
     */
    private function staleRegionScan(string $status = 'running', int $minutesAgo = 120): Scan
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'status' => $status,
            'scan_types' => ['ec2'],
            'started_at' => now()->subMinutes($minutesAgo),
            'expected_regions_count' => 2,
        ]);

        // One region reported in; the other never did.
        ScanDetail::factory()->ec2('us-east-1')->create(['scan_id' => $scan->id]);

        return $scan;
    }

    public function test_it_reports_when_there_is_nothing_to_do(): void
    {
        $this->artisan('scans:mark-stale-region-complete')
            ->expectsOutputToContain('No scans found')
            ->assertSuccessful();
    }

    public function test_a_recently_started_scan_is_left_alone(): void
    {
        $scan = $this->staleRegionScan(minutesAgo: 5);

        $this->artisan('scans:mark-stale-region-complete')
            ->expectsOutputToContain('No scans found')
            ->assertSuccessful();

        $this->assertEquals('running', $scan->fresh()->status);
    }

    public function test_a_scan_that_never_started_is_left_alone(): void
    {
        $scan = $this->staleRegionScan();
        $scan->update(['started_at' => null]);

        $this->artisan('scans:mark-stale-region-complete')
            ->expectsOutputToContain('No scans found')
            ->assertSuccessful();
    }

    public function test_a_completed_scan_is_left_alone(): void
    {
        $scan = $this->staleRegionScan(status: 'completed');

        $this->artisan('scans:mark-stale-region-complete')
            ->expectsOutputToContain('No scans found')
            ->assertSuccessful();

        $this->assertEquals('completed', $scan->fresh()->status);
    }

    public function test_a_stale_region_scan_is_marked_complete(): void
    {
        $scan = $this->staleRegionScan();

        $this->artisan('scans:mark-stale-region-complete')
            ->expectsOutputToContain('Found 1 scan(s)')
            ->expectsOutputToContain('Done.')
            ->assertSuccessful();

        $this->assertEquals('completed', $scan->fresh()->status);
    }

    /**
     * A scan whose orchestrator died before flipping it to 'running' still needs
     * completing, so 'pending' is swept too.
     */
    public function test_a_stale_pending_scan_is_swept_as_well(): void
    {
        $scan = $this->staleRegionScan(status: 'pending');

        $this->artisan('scans:mark-stale-region-complete')->assertSuccessful();

        $this->assertEquals('completed', $scan->fresh()->status);
    }

    public function test_dry_run_reports_without_changing_anything(): void
    {
        $scan = $this->staleRegionScan();

        $this->artisan('scans:mark-stale-region-complete', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 scan(s)')
            ->expectsOutputToContain('Dry run: no changes made.')
            ->assertSuccessful();

        $this->assertEquals('running', $scan->fresh()->status);
    }

    /**
     * --minutes controls which scans the command *selects*.
     */
    public function test_the_minutes_option_widens_the_selection(): void
    {
        $this->staleRegionScan(minutesAgo: 20);

        $this->artisan('scans:mark-stale-region-complete', ['--minutes' => 60])
            ->expectsOutputToContain('No scans found')
            ->assertSuccessful();

        $this->artisan('scans:mark-stale-region-complete', ['--minutes' => 10])
            ->expectsOutputToContain('Found 1 scan(s)')
            ->assertSuccessful();
    }

    /**
     * ...and now also whether they are settled. The model used to apply its own fixed
     * 60-minute timeout on top, so lowering --minutes selected a scan the model then
     * declined to finish. That second, hidden threshold is gone with the counter (#65):
     * the option means what it says.
     */
    public function test_lowering_minutes_now_settles_the_scan_it_selects(): void
    {
        $scan = $this->staleRegionScan(minutesAgo: 20);

        $this->artisan('scans:mark-stale-region-complete', ['--minutes' => 10])
            ->expectsOutputToContain('Settled scan')
            ->assertSuccessful();

        $this->assertEquals('completed', $scan->fresh()->status);
    }

    /**
     * Everything this sweep settles is partial, whatever has reported in. It only runs
     * when the batch callback did not, so work is unaccounted for by definition — and a
     * fallback path on a security scanner must never be able to say "all clear".
     */
    public function test_anything_the_sweep_settles_is_marked_partial(): void
    {
        $scan = $this->staleRegionScan(minutesAgo: 20);
        ScanDetail::factory()->ec2('eu-west-1')->create(['scan_id' => $scan->id]);

        $this->artisan('scans:mark-stale-region-complete', ['--minutes' => 10])
            ->assertSuccessful();

        $scan->refresh();
        $this->assertEquals('completed', $scan->status);
        $this->assertTrue($scan->is_partial, 'a swept scan is never a clean bill of health');
        $this->assertStringContainsString('partial', $scan->error_message);
    }

    /**
     * A scan with no region-based services is completed inline by the orchestrator, so
     * one still running is a different fault. It is now filtered out of the selection
     * rather than listed and then skipped — the command should not report scans it has
     * no intention of touching.
     */
    public function test_a_stale_non_region_scan_is_not_selected(): void
    {
        $scan = $this->staleRegionScan();
        $scan->update(['scan_types' => ['iam']]);

        $this->artisan('scans:mark-stale-region-complete')
            ->expectsOutputToContain('No scans found')
            ->assertSuccessful();

        $this->assertEquals('running', $scan->fresh()->status);
    }
}
