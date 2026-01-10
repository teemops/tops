<?php

namespace Database\Factories;

use App\Models\AwsAccount;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AwsAccount>
 */
class AwsAccountFactory extends Factory
{
    protected $model = AwsAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory()->create();
        
        return [
            'organization_id' => $organization->id,
            'name' => 'AWS Account ' . $this->faker->numerify('##########'),
            'aws_account_id' => $this->faker->numerify('##########'),
            'iam_role_arn' => 'arn:aws:iam::' . $this->faker->numerify('##########') . ':role/TeemOps',
            'external_id' => $this->faker->uuid(),
            'unique_id' => $organization->org_id, // Derived from org_id
            'status' => 'pending',
        ];
    }

    /**
     * Indicate that the account is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'aws_account_id' => null,
            'iam_role_arn' => null,
        ]);
    }

    /**
     * Indicate that the account is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'aws_account_id' => $this->faker->numerify('##########'),
            'iam_role_arn' => 'arn:aws:iam::' . $this->faker->numerify('##########') . ':role/TeemOps',
        ]);
    }

    /**
     * Indicate that the account has an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
        ]);
    }
}
