<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support MODIFY COLUMN or DROP INDEX directly
            // For SQLite, we'll recreate the table with the new structure
            $this->migrateSqlite();
        } else {
            // MySQL/MariaDB/PostgreSQL
            $this->migrateMysql();
        }
    }

    /**
     * Migrate for MySQL/MariaDB
     */
    private function migrateMysql(): void
    {
        // Remove unique constraint on unique_id if it exists
        // (Multiple accounts in same org will share the same unique_id)
        try {
            DB::statement("ALTER TABLE aws_accounts DROP INDEX aws_accounts_unique_id_unique");
        } catch (\Exception $e) {
            // Index doesn't exist, which is fine
        }

        // Update status enum: change 'active' to 'completed'
        DB::statement("ALTER TABLE aws_accounts MODIFY COLUMN status ENUM('pending', 'completed', 'error') DEFAULT 'pending'");
        
        // Make aws_account_id and iam_role_arn nullable (they're null until CloudFormation completes)
        DB::statement("ALTER TABLE aws_accounts MODIFY COLUMN aws_account_id VARCHAR(12) NULL");
        DB::statement("ALTER TABLE aws_accounts MODIFY COLUMN iam_role_arn TEXT NULL");

        // Add composite index for SQS message matching (unique_id + external_id)
        // Check if index exists first
        $indexExists = DB::select("SHOW INDEX FROM aws_accounts WHERE Key_name = 'aws_accounts_unique_id_external_id_index'");
        if (empty($indexExists)) {
            Schema::table('aws_accounts', function (Blueprint $table) {
                $table->index(['unique_id', 'external_id'], 'aws_accounts_unique_id_external_id_index');
            });
        }
    }

    /**
     * Migrate for SQLite (recreate table)
     */
    private function migrateSqlite(): void
    {
        // SQLite doesn't support MODIFY COLUMN or DROP INDEX directly
        // The original migration (2026_01_09_023518_create_aws_accounts_table.php) already
        // creates the table with the correct structure (nullable fields, correct enum, composite index)
        // So for SQLite, we only need to ensure the composite index exists
        
        // Check if table exists
        if (!Schema::hasTable('aws_accounts')) {
            // Table doesn't exist yet, nothing to update
            return;
        }

        // Check if composite index exists and add it if it doesn't
        try {
            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name='aws_accounts_unique_id_external_id_index'");
            if (empty($indexes)) {
                Schema::table('aws_accounts', function (Blueprint $table) {
                    $table->index(['unique_id', 'external_id'], 'aws_accounts_unique_id_external_id_index');
                });
            }
        } catch (\Exception $e) {
            // Index might already exist or there's an issue, which is fine
            // The original migration should have created it anyway
        }
        
        // Note: For SQLite, we don't need to:
        // - Drop unique constraint on unique_id (it doesn't exist in the new structure)
        // - Modify enum (SQLite doesn't enforce ENUMs strictly, and original migration has correct values)
        // - Modify nullable columns (original migration already has them as nullable)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('aws_accounts', function (Blueprint $table) {
            // Remove composite index
            try {
                $table->dropIndex('aws_accounts_unique_id_external_id_index');
            } catch (\Exception $e) {
                // Index doesn't exist, which is fine
            }
        });

        if ($driver !== 'sqlite') {
            // Revert status enum back to 'active' (MySQL/MariaDB only)
            DB::statement("ALTER TABLE aws_accounts MODIFY COLUMN status ENUM('pending', 'active', 'error') DEFAULT 'pending'");
            
            // Revert nullable fields back to NOT NULL (if needed)
            // Note: This may fail if there are NULL values
            // DB::statement("ALTER TABLE aws_accounts MODIFY COLUMN aws_account_id VARCHAR(12) NOT NULL");
            // DB::statement("ALTER TABLE aws_accounts MODIFY COLUMN iam_role_arn TEXT NOT NULL");
        }
    }
};
