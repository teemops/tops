<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_password_and_remember_token_are_hidden_from_serialization(): void
    {
        $user = User::factory()->create();

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
    }

    public function test_the_password_is_hashed_on_assignment(): void
    {
        $user = User::factory()->create(['password' => 'plain-text-password']);

        $this->assertNotEquals('plain-text-password', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('plain-text-password', $user->password));
    }

    public function test_email_verified_at_is_cast_to_a_date(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    public function test_owned_organizations_lists_only_organizations_the_user_owns(): void
    {
        $user = User::factory()->create();
        Organization::factory()->count(2)->create(['user_id' => $user->id]);
        Organization::factory()->create();

        $this->assertCount(2, $user->ownedOrganizations);
    }

    public function test_member_organizations_exposes_the_pivot_role(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'auditor',
        ]);

        $memberships = $user->memberOrganizations;

        $this->assertCount(1, $memberships);
        $this->assertEquals('auditor', $memberships->first()->pivot->role);
    }

    /**
     * organizations() merges owned and member-of, deduplicating the common case
     * where the owner also has a member record.
     */
    public function test_organizations_merges_owned_and_member_organizations_without_duplicates(): void
    {
        $user = User::factory()->create();

        $owned = Organization::factory()->create(['user_id' => $user->id]);
        OrganizationMember::factory()->create([
            'organization_id' => $owned->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $joined = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $joined->id,
            'user_id' => $user->id,
            'role' => 'viewer',
        ]);

        Organization::factory()->create();

        $organizations = $user->organizations();

        $this->assertCount(2, $organizations);
        $this->assertEqualsCanonicalizing(
            [$owned->id, $joined->id],
            $organizations->pluck('id')->all()
        );
    }

    public function test_organizations_is_empty_for_a_user_with_none(): void
    {
        $this->assertCount(0, User::factory()->create()->organizations());
    }
}
