<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data migration: store iam_role_arn as plaintext.
 *
 * See roadmap decision D-9. An IAM role ARN is an identifier, not a credential —
 * assuming a role additionally requires sts:AssumeRole permission, a trust policy
 * naming the caller, and the ExternalId, which is stored plaintext and indexed
 * alongside it. Encrypting one and not the other bought inconsistent protection,
 * and in a single-host deployment APP_KEY sits in .env next to the database
 * anyway. Encryption at rest is delegated to the host.
 *
 * MUST run before APP_KEY is rotated. It decrypts with the current key, so
 * rotating first would leave ciphertext that nothing can read.
 *
 * Uses the query builder throughout, deliberately: at the time this runs the
 * model's encrypting mutator may or may not still exist, and going through
 * Eloquent would re-encrypt on write.
 */
return new class extends Migration
{
    /**
     * A decrypted value must look like this before we trust it. Guards against
     * writing ciphertext back into the column as though it were plaintext.
     */
    private const ARN_PREFIX = 'arn:aws:iam::';

    public function up(): void
    {
        $converted = 0;
        $alreadyPlain = 0;
        $failed = [];

        DB::table('aws_accounts')
            ->select('id', 'iam_role_arn')
            ->whereNotNull('iam_role_arn')
            ->where('iam_role_arn', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$converted, &$alreadyPlain, &$failed) {
                foreach ($rows as $row) {
                    // Idempotent: re-running finds plaintext and leaves it alone.
                    if (str_starts_with($row->iam_role_arn, self::ARN_PREFIX)) {
                        $alreadyPlain++;
                        continue;
                    }

                    try {
                        $plain = Crypt::decryptString($row->iam_role_arn);
                    } catch (\Throwable $e) {
                        // Already unreadable before this migration ran — the old
                        // accessor returned ciphertext silently, so this may have
                        // been broken for a while. Leave it untouched rather than
                        // corrupting it further, and report it at the end.
                        $failed[] = $row->id;
                        continue;
                    }

                    if (! str_starts_with($plain, self::ARN_PREFIX)) {
                        $failed[] = $row->id;
                        continue;
                    }

                    DB::table('aws_accounts')
                        ->where('id', $row->id)
                        ->update(['iam_role_arn' => $plain]);

                    $converted++;
                }
            });

        $summary = sprintf(
            'iam_role_arn decryption: %d converted, %d already plaintext, %d could not be decrypted.',
            $converted,
            $alreadyPlain,
            count($failed)
        );

        // Migrations run unattended from the container entrypoint, so this has to
        // reach the log rather than only stdout.
        Log::info($summary);
        echo $summary . PHP_EOL;

        if ($failed !== []) {
            $message = 'These aws_accounts rows still hold an unreadable iam_role_arn and need '
                . 'the account re-linked: ' . implode(', ', $failed);

            Log::warning($message);
            echo $message . PHP_EOL;
        }
    }

    /**
     * Re-encrypt, so the migration is reversible while APP_KEY is unchanged.
     * If the key has been rotated since `up()` ran, this will encrypt under the
     * new key — which is correct, but the old ciphertext is not recoverable.
     */
    public function down(): void
    {
        DB::table('aws_accounts')
            ->select('id', 'iam_role_arn')
            ->whereNotNull('iam_role_arn')
            ->where('iam_role_arn', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    if (! str_starts_with($row->iam_role_arn, self::ARN_PREFIX)) {
                        continue; // Already ciphertext.
                    }

                    DB::table('aws_accounts')
                        ->where('id', $row->id)
                        ->update(['iam_role_arn' => Crypt::encryptString($row->iam_role_arn)]);
                }
            });
    }
};
