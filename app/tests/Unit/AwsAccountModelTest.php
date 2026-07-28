<?php

namespace Tests\Unit;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AwsAccountModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_external_id_is_generated_on_creation(): void
    {
        $organization = Organization::factory()->create();

        $account = AwsAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Prod',
            'unique_id' => $organization->org_id,
            'status' => 'pending',
        ]);

        $this->assertNotEmpty($account->external_id);
    }

    public function test_an_explicit_external_id_is_preserved(): void
    {
        $account = AwsAccount::factory()->create(['external_id' => 'fixed-external-id']);

        $this->assertEquals('fixed-external-id', $account->external_id);
    }

    /**
     * The role ARN is a credential-equivalent, so it must not be readable from
     * the raw column.
     */
    public function test_the_iam_role_arn_is_encrypted_at_rest(): void
    {
        $arn = 'arn:aws:iam::123456789012:role/TeemOps';
        $account = AwsAccount::factory()->create(['iam_role_arn' => $arn]);

        $stored = DB::table('aws_accounts')->where('id', $account->id)->value('iam_role_arn');

        $this->assertNotEquals($arn, $stored);
        $this->assertEquals($arn, Crypt::decryptString($stored));
    }

    public function test_the_iam_role_arn_round_trips_through_the_accessor(): void
    {
        $arn = 'arn:aws:iam::123456789012:role/TeemOps';
        $account = AwsAccount::factory()->create(['iam_role_arn' => $arn]);

        $this->assertEquals($arn, $account->fresh()->iam_role_arn);
    }

    public function test_a_null_iam_role_arn_is_stored_as_null(): void
    {
        $account = AwsAccount::factory()->pending()->create();

        $this->assertNull(DB::table('aws_accounts')->where('id', $account->id)->value('iam_role_arn'));
    }

    /**
     * Rows written before encryption was introduced are returned as-is rather
     * than blowing up on decrypt.
     */
    public function test_an_undecryptable_value_is_returned_unchanged(): void
    {
        $account = AwsAccount::factory()->create();
        DB::table('aws_accounts')->where('id', $account->id)->update([
            'iam_role_arn' => 'arn:aws:iam::123456789012:role/PlaintextLegacy',
        ]);

        $this->assertEquals('arn:aws:iam::123456789012:role/PlaintextLegacy', $account->fresh()->iam_role_arn);
    }

    public function test_last_scan_at_is_cast_to_a_date(): void
    {
        $account = AwsAccount::factory()->create(['last_scan_at' => now()]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $account->fresh()->last_scan_at);
    }

    public function test_the_account_is_soft_deleted(): void
    {
        $account = AwsAccount::factory()->create();

        $account->delete();

        $this->assertSoftDeleted('aws_accounts', ['id' => $account->id]);
    }

    public function test_relationships_resolve(): void
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->create(['organization_id' => $organization->id]);
        Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $account->id,
        ]);

        $this->assertTrue($account->organization->is($organization));
        $this->assertCount(1, $account->scans);
    }
}
