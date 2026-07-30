<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schema-only counterpart to the iam_role_arn data migration (D-9).
 *
 * The column comment still read "Encrypted IAM Role ARN", which is now wrong and
 * is exactly the kind of stale comment that leads someone to assume protection
 * that isn't there.
 *
 * Kept separate from the data migration per docs/practices/database.md. Guarded
 * on the MySQL driver: SQLite backs the test suite and has no column comments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE aws_accounts MODIFY COLUMN iam_role_arn TEXT NULL "
            . "COMMENT 'IAM Role ARN, plaintext — see roadmap D-9 (null until CloudFormation completes)'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE aws_accounts MODIFY COLUMN iam_role_arn TEXT NULL "
            . "COMMENT 'Encrypted IAM Role ARN (null until CloudFormation completes)'"
        );
    }
};
