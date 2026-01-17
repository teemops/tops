<?php

namespace Database\Factories;

use App\Models\Scan;
use App\Models\ScanResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScanResult>
 */
class ScanResultFactory extends Factory
{
    protected $model = ScanResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scan_id' => Scan::factory(),
            'severity' => $this->faker->randomElement(['critical', 'high', 'medium', 'low']),
            'service' => $this->faker->randomElement(['s3', 'iam', 'ec2', 'rds']),
            'resource_type' => $this->faker->word(),
            'resource_id' => $this->faker->uuid(),
            'finding_type' => $this->faker->word(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'remediation' => $this->faker->optional()->paragraph(),
            'status' => 'open',
        ];
    }

    /**
     * Indicate that the result is critical severity.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
        ]);
    }

    /**
     * Indicate that the result is high severity.
     */
    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'high',
        ]);
    }

    /**
     * Indicate that the result is for a specific service.
     */
    public function forService(string $service): static
    {
        return $this->state(fn (array $attributes) => [
            'service' => $service,
        ]);
    }
}
