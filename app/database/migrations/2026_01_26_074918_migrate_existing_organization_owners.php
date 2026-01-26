<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates OrganizationMember records with role='owner' for all existing organizations.
     * This ensures owner role is tracked in both places (organizations.user_id and organization_members).
     */
    public function up(): void
    {
        // Get all existing organizations
        $organizations = DB::table('organizations')->get();
        
        foreach ($organizations as $org) {
            // Check if member record already exists
            $existingMember = DB::table('organization_members')
                ->where('organization_id', $org->id)
                ->where('user_id', $org->user_id)
                ->first();
            
            // Only create if it doesn't exist
            if (!$existingMember) {
                DB::table('organization_members')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'organization_id' => $org->id,
                    'user_id' => $org->user_id,
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all owner member records (keep organizations.user_id as is)
        DB::table('organization_members')
            ->where('role', 'owner')
            ->delete();
    }
};
