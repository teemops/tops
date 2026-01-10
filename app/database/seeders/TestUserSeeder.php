<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update test user for Playwright E2E tests
        $user = User::updateOrCreate(
            ['email' => 'test@auditaws.cloud'],
            [
                'name' => 'Test User',
                'email' => 'test@auditaws.cloud',
                'password' => Hash::make('password'),
                'email_verified_at' => now(), // Pre-verified for testing
            ]
        );

        // Ensure user has a default organization
        if ($user->organizations()->count() === 0) {
            $user->organizations()->create([
                'name' => "Test User's Organization",
                'is_default' => true,
            ]);
        }

        $this->command->info('Test user created/updated: test@auditaws.cloud');
        $this->command->info('Password: password');
    }
}

