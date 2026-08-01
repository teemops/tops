<?php

namespace Tests\Unit;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The D-1 data migration collapses one-row-per-scan into one-row-per-problem, which means
 * it deletes rows on a real install. On a security product that is worth testing directly
 * rather than trusting to a clean-database migration run — RefreshDatabase starts empty,
 * so nothing else here exercises a single line of it.
 */
class BackfillDurableFindingIdentitiesTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require base_path('database/migrations/2026_08_01_100100_backfill_durable_finding_identities.php');
        $migration->up();
    }

    /**
     * Insert a finding the way the pre-D-1 code did: attached to a scan, with no identity
     * and no lifecycle columns.
     */
    private function legacyFinding(Scan $scan, array $overrides = []): string
    {
        $id = (string) Str::uuid();

        DB::table('scan_results')->insert(array_merge([
            'id' => $id,
            'scan_id' => $scan->id,
            'severity' => 'high',
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'alice',
            'finding_type' => 'tops-iam-001',
            'title' => 'IAM user without MFA',
            'description' => 'No MFA device',
            'remediation' => 'Enable MFA',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function accountWithScans(int $scans): array
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $created = [];
        for ($i = 0; $i < $scans; $i++) {
            $created[] = Scan::factory()->create([
                'organization_id' => $organization->id,
                'aws_account_id' => $account->id,
                'status' => 'completed',
            ]);
        }

        return [$organization, $account, $created];
    }

    public function test_it_gives_existing_findings_their_account_organization_and_identity(): void
    {
        [$organization, $account, $scans] = $this->accountWithScans(1);
        $id = $this->legacyFinding($scans[0]);

        $this->runMigration();

        $finding = ScanResult::find($id);
        $this->assertSame($organization->id, $finding->organization_id);
        $this->assertSame($account->id, $finding->aws_account_id);
        $this->assertSame(
            ScanResult::identityHash($organization->id, $account->id, 'iam', 'user', 'alice', 'tops-iam-001'),
            $finding->identity_hash,
        );
        $this->assertNotNull($finding->first_seen_at);
        $this->assertSame($scans[0]->id, $finding->last_seen_scan_id);
    }

    /**
     * The reason the unique index lives in a later migration.
     */
    public function test_it_collapses_the_same_problem_seen_by_three_scans_into_one_finding(): void
    {
        [, , $scans] = $this->accountWithScans(3);

        $first = $this->legacyFinding($scans[0], ['created_at' => now()->subDays(3), 'title' => 'Oldest']);
        $this->legacyFinding($scans[1], ['created_at' => now()->subDays(2), 'title' => 'Middle']);
        $this->legacyFinding($scans[2], ['created_at' => now()->subDay(), 'title' => 'Newest', 'severity' => 'critical']);

        $this->runMigration();

        $this->assertSame(1, ScanResult::count(), 'Three scans of one unchanged problem is one finding.');

        $finding = ScanResult::first();
        $this->assertSame($first, $finding->id, 'The earliest row is kept, so any id already in a URL still resolves.');
        $this->assertSame('Newest', $finding->title, 'Content comes from the most recent observation.');
        $this->assertSame('critical', $finding->severity);
        $this->assertSame($scans[2]->id, $finding->last_seen_scan_id);
    }

    /**
     * A human decision must survive the collapse — it is the one thing a scan does not own.
     */
    public function test_an_ignored_copy_wins_over_whichever_row_sorts_last(): void
    {
        [, , $scans] = $this->accountWithScans(2);

        $this->legacyFinding($scans[0], ['created_at' => now()->subDays(2), 'status' => 'ignored']);
        $this->legacyFinding($scans[1], ['created_at' => now()->subDay(), 'status' => 'open']);

        $this->runMigration();

        $this->assertSame(1, ScanResult::count());
        $this->assertSame('ignored', ScanResult::first()->status, 'Collapsing must not silently un-ignore a finding.');
    }

    public function test_different_resources_are_not_collapsed_together(): void
    {
        [, , $scans] = $this->accountWithScans(1);

        $this->legacyFinding($scans[0], ['resource_id' => 'alice']);
        $this->legacyFinding($scans[0], ['resource_id' => 'bob']);
        $this->legacyFinding($scans[0], ['resource_id' => 'alice', 'finding_type' => 'tops-iam-002']);

        $this->runMigration();

        $this->assertSame(3, ScanResult::count(), 'Identity is resource *and* rule — neither alone.');
    }

    /**
     * Two tenants scanning the same AWS account must not have their findings merged.
     */
    public function test_findings_in_different_organizations_are_never_collapsed_together(): void
    {
        [, , $scansA] = $this->accountWithScans(1);
        [, , $scansB] = $this->accountWithScans(1);

        $this->legacyFinding($scansA[0]);
        $this->legacyFinding($scansB[0]);

        $this->runMigration();

        $this->assertSame(2, ScanResult::count(), 'The organization is part of the identity.');
    }

    /**
     * The migration has to leave the table in a state the unique index can be added to.
     */
    public function test_after_the_migration_every_identity_is_unique(): void
    {
        [, , $scans] = $this->accountWithScans(2);
        $this->legacyFinding($scans[0], ['created_at' => now()->subDay()]);
        $this->legacyFinding($scans[1]);

        $this->runMigration();

        $duplicates = DB::table('scan_results')
            ->select('identity_hash')
            ->groupBy('identity_hash')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicates);
    }
}
