<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationMember>
 */
class OrganizationMemberFactory extends Factory
{
    protected $model = OrganizationMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => 'viewer',
        ];
    }

    /**
     * Give the member a specific role.
     */
    public function role(?string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    /**
     * A member who has joined but has not been assigned a role yet.
     */
    public function withoutRole(): static
    {
        return $this->role(null);
    }
}
