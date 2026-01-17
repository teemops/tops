<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Check if column already exists (in case migration was partially run)
        if (!Schema::hasColumn('scans', 'scan_types')) {
            Schema::table('scans', function (Blueprint $table) {
                // Add scan_types JSON column to store array of scan types
                $table->json('scan_types')->nullable()->after('aws_account_id');
            });
        }

        // Update status enum to include 'cancelled' (MySQL/MariaDB only)
        // SQLite doesn't enforce ENUMs strictly, so we skip this for SQLite
        // Note: We don't add an index on scan_types directly as MySQL doesn't support
        // direct indexing on JSON columns. If indexing is needed, use JSON functions
        // in queries or create a generated column with an index.
        if ($driver !== 'sqlite') {
            // Check current enum values to avoid errors if already updated
            $enumCheck = DB::select("SHOW COLUMNS FROM scans WHERE Field = 'status'");
            if (!empty($enumCheck)) {
                $enumDefinition = $enumCheck[0]->Type ?? '';
                if (strpos($enumDefinition, 'cancelled') === false) {
                    DB::statement("ALTER TABLE scans MODIFY COLUMN status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending'");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn('scan_types');
        });

        // Revert status enum (remove 'cancelled') - MySQL/MariaDB only
        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE scans MODIFY COLUMN status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending'");
        }
    }
};
