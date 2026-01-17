<?php

namespace Database\Factories;

use App\Models\Scan;
use App\Models\ScanDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScanDetail>
 */
class ScanDetailFactory extends Factory
{
    protected $model = ScanDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scan_id' => Scan::factory(),
            'service' => $this->faker->randomElement(['iam', 's3', 'ec2', 'rds']),
            'resource_type' => $this->faker->word(),
            'resource_id' => $this->faker->uuid(),
            'api_method' => $this->faker->word(),
            'raw_data' => [
                'key' => 'value',
                'data' => $this->faker->words(3),
            ],
            'region' => null,
        ];
    }

    /**
     * Indicate that the detail is for a specific service.
     */
    public function forService(string $service): static
    {
        return $this->state(fn (array $attributes) => [
            'service' => $service,
        ]);
    }

    /**
     * Indicate that the detail is for a specific region.
     */
    public function forRegion(string $region): static
    {
        return $this->state(fn (array $attributes) => [
            'region' => $region,
        ]);
    }

    /**
     * Indicate that this is an EC2 scan detail.
     */
    public function ec2(string $region = 'us-east-1'): static
    {
        return $this->state(fn (array $attributes) => [
            'service' => 'ec2',
            'resource_type' => 'instance',
            'api_method' => 'DescribeInstances',
            'region' => $region,
        ]);
    }

    /**
     * Indicate that this is an S3 scan detail.
     */
    public function s3(string $region = 'us-east-1'): static
    {
        return $this->state(fn (array $attributes) => [
            'service' => 's3',
            'resource_type' => 'bucket',
            'api_method' => 'ListBuckets',
            'region' => $region,
        ]);
    }

    /**
     * Indicate that this is an IAM scan detail (global, no region).
     */
    public function iam(): static
    {
        return $this->state(fn (array $attributes) => [
            'service' => 'iam',
            'resource_type' => 'user',
            'api_method' => 'ListUsers',
            'region' => null,
        ]);
    }

    /**
     * Indicate that this is an RDS scan detail.
     */
    public function rds(string $region = 'us-east-1'): static
    {
        return $this->state(fn (array $attributes) => [
            'service' => 'rds',
            'resource_type' => 'db_instance',
            'api_method' => 'DescribeDBInstances',
            'region' => $region,
        ]);
    }
}
