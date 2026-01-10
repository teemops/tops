<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Seed test user for Playwright E2E tests (only in development/testing)
        if (app()->environment(['local', 'testing'])) {
            $this->call(TestUserSeeder::class);
        }
    }
}
