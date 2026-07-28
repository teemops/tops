<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationMemberModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_resolve(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($member->organization->is($organization));
        $this->assertTrue($member->user->is($user));
    }

    public function test_it_uses_a_uuid_primary_key(): void
    {
        $member = OrganizationMember::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $member->id
        );
    }

    public function test_a_member_may_have_no_role_yet(): void
    {
        $member = OrganizationMember::factory()->withoutRole()->create();

        $this->assertNull($member->fresh()->role);
    }

    /**
     * A user can only hold one membership per organization.
     */
    public function test_a_user_cannot_be_added_to_the_same_organization_twice(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_the_same_user_can_belong_to_different_organizations(): void
    {
        $user = User::factory()->create();

        OrganizationMember::factory()->create(['user_id' => $user->id]);
        OrganizationMember::factory()->create(['user_id' => $user->id]);

        $this->assertEquals(2, OrganizationMember::where('user_id', $user->id)->count());
    }
}
