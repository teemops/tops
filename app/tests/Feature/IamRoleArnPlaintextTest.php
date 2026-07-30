<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * iam_role_arn is stored plaintext (roadmap D-9).
 *
 * The existing controller and job tests read the ARN back through the model, so
 * they pass whether it is encrypted or not. These assert the thing that actually
 * changed: what is on disk, and that the data migration converts an instance
 * that already holds ciphertext.
 */
class IamRoleArnPlaintextTest extends TestCase
{
    use RefreshDatabase;

    private const ARN = 'arn:aws:iam::123456789012:role/TeemOps';

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_07_30_100000_decrypt_aws_account_iam_role_arns.php'
        );
    }

    private function rawArn(string $id): ?string
    {
        return DB::table('aws_accounts')->where('id', $id)->value('iam_role_arn');
    }

    public function test_arn_is_written_to_the_database_as_plaintext(): void
    {
        $account = AwsAccount::factory()->for(Organization::factory())->create([
            'iam_role_arn' => self::ARN,
        ]);

        // Read past Eloquent — an accessor could otherwise mask ciphertext on disk.
        $this->assertSame(self::ARN, $this->rawArn($account->id));
    }

    public function test_arn_reads_back_unchanged_through_the_model(): void
    {
        $account = AwsAccount::factory()->for(Organization::factory())->create([
            'iam_role_arn' => self::ARN,
        ]);

        $this->assertSame(self::ARN, $account->fresh()->iam_role_arn);
    }

    public function test_null_arn_stays_null(): void
    {
        $account = AwsAccount::factory()->for(Organization::factory())->create([
            'iam_role_arn' => null,
        ]);

        $this->assertNull($this->rawArn($account->id));
        $this->assertNull($account->fresh()->iam_role_arn);
    }

    public function test_migration_decrypts_an_existing_encrypted_arn(): void
    {
        $account = AwsAccount::factory()->for(Organization::factory())->create([
            'iam_role_arn' => self::ARN,
        ]);

        // Put the row back into the pre-D-9 state.
        DB::table('aws_accounts')->where('id', $account->id)->update([
            'iam_role_arn' => Crypt::encryptString(self::ARN),
        ]);
        $this->assertNotSame(self::ARN, $this->rawArn($account->id));

        $this->migration()->up();

        $this->assertSame(self::ARN, $this->rawArn($account->id));
    }

    public function test_migration_is_idempotent(): void
    {
        $account = AwsAccount::factory()->for(Organization::factory())->create([
            'iam_role_arn' => self::ARN,
        ]);

        DB::table('aws_accounts')->where('id', $account->id)->update([
            'iam_role_arn' => Crypt::encryptString(self::ARN),
        ]);

        $this->migration()->up();
        $this->migration()->up();

        // A second pass must not treat plaintext as ciphertext, nor re-wrap it.
        $this->assertSame(self::ARN, $this->rawArn($account->id));
    }

    public function test_migration_leaves_undecryptable_values_untouched(): void
    {
        $account = AwsAccount::factory()->for(Organization::factory())->create([
            'iam_role_arn' => self::ARN,
        ]);

        // Ciphertext from a key we no longer hold looks exactly like this.
        DB::table('aws_accounts')->where('id', $account->id)->update([
            'iam_role_arn' => 'eyJpdiI6ImJvZ3VzIiwidmFsdWUiOiJib2d1cyIsIm1hYyI6ImJvZ3VzIn0=',
        ]);
        $before = $this->rawArn($account->id);

        $this->migration()->up();

        // Reported, not corrupted — overwriting would destroy the only evidence
        // of what the value was.
        $this->assertSame($before, $this->rawArn($account->id));
    }

    public function test_migration_down_restores_ciphertext(): void
    {
        $account = AwsAccount::factory()->for(Organization::factory())->create([
            'iam_role_arn' => self::ARN,
        ]);

        $this->migration()->down();

        $raw = $this->rawArn($account->id);
        $this->assertNotSame(self::ARN, $raw);
        $this->assertSame(self::ARN, Crypt::decryptString($raw));
    }
}
