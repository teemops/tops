<?php

namespace Database\Factories;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Scan>
 */
class ScanFactory extends Factory
{
    protected $model = Scan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'aws_account_id' => AwsAccount::factory(),
            'scan_types' => ['iam', 's3'],
            'status' => 'pending',
        ];
    }

    /**
     * Indicate that the scan is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the scan is running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the scan is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the scan has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'error_message' => 'Test error message',
        ]);
    }

    /**
     * Indicate that the scan is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Set specific scan types.
     */
    public function withScanTypes(array $scanTypes): static
    {
        return $this->state(fn (array $attributes) => [
            'scan_types' => $scanTypes,
        ]);
    }
}
