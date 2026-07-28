<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/api/organizations')->assertStatus(401);
    }

    public function test_it_lists_owned_and_joined_organizations_with_the_users_role(): void
    {
        $user = User::factory()->create();
        $owned = Organization::factory()->create(['user_id' => $user->id, 'name' => 'Owned Co']);
        $joined = Organization::factory()->create(['name' => 'Joined Co']);
        OrganizationMember::factory()->create([
            'organization_id' => $joined->id,
            'user_id' => $user->id,
            'role' => 'auditor',
        ]);
        Organization::factory()->create(['name' => 'Someone Else Co']);

        $response = $this->actingAs($user)->getJson('/api/organizations');

        $response->assertOk();
        $organizations = collect($response->json('organizations'));

        $this->assertCount(2, $organizations);
        $this->assertEquals('owner', $organizations->firstWhere('id', $owned->id)['role']);
        $this->assertEquals('auditor', $organizations->firstWhere('id', $joined->id)['role']);
        $this->assertNull($organizations->firstWhere('name', 'Someone Else Co'));
    }

    public function test_the_listing_includes_the_aws_account_count(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);
        AwsAccount::factory()->count(3)->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($user)->getJson('/api/organizations');

        $this->assertEquals(3, $response->json('organizations.0.aws_accounts_count'));
    }

    public function test_it_returns_the_current_organization_from_context(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->getJson('/api/organizations/current');

        $response->assertOk()
            ->assertJson([
                'id' => $organization->id,
                'org_id' => $organization->org_id,
                'name' => $organization->name,
            ]);
    }

    public function test_it_shows_an_organization_the_user_owns(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/organizations/{$organization->org_id}");

        $response->assertOk()
            ->assertJsonStructure(['id', 'name', 'org_id', 'is_default', 'aws_accounts_count', 'created_at', 'updated_at']);
    }

    public function test_showing_an_organization_the_user_is_not_in_is_denied(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}")
            ->assertStatus(403);
    }

    public function test_showing_an_unknown_organization_is_a_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/organizations/no-such-org')
            ->assertStatus(404);
    }

    public function test_it_creates_an_organization_and_an_owner_member_record(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/organizations', ['name' => 'New Team']);

        $response->assertStatus(201)->assertJson(['name' => 'New Team']);

        $this->assertDatabaseHas('organizations', ['name' => 'New Team', 'user_id' => $user->id]);
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $response->json('id'),
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    /**
     * Organizations created through the API are never the default: by the time the
     * request reaches the controller, SetOrganizationContext has already given a
     * brand-new user a default organization of their own.
     */
    public function test_an_organization_created_via_the_api_is_not_the_default(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson('/api/organizations', ['name' => 'First']);
        $this->assertFalse($first->json('is_default'));

        $default = Organization::where('user_id', $user->id)->where('is_default', true)->first();
        $this->assertNotNull($default);
        $this->assertNotEquals($first->json('id'), $default->id);

        $second = $this->actingAs($user)->postJson('/api/organizations', ['name' => 'Second']);
        $this->assertFalse($second->json('is_default'));
    }

    public function test_creating_an_organization_validates_the_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/organizations', ['name' => 'A'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_an_owner_can_rename_an_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/organizations/{$organization->org_id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJson(['name' => 'Renamed']);

        $this->assertEquals('Renamed', $organization->fresh()->name);
    }

    public function test_an_administrator_can_rename_an_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'administrator',
        ]);

        $this->actingAs($user)
            ->putJson("/api/organizations/{$organization->org_id}", ['name' => 'Renamed'])
            ->assertOk();
    }

    public function test_an_auditor_cannot_rename_an_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'auditor',
        ]);

        $this->actingAs($user)
            ->putJson("/api/organizations/{$organization->org_id}", ['name' => 'Renamed'])
            ->assertStatus(403);

        $this->assertNotEquals('Renamed', $organization->fresh()->name);
    }

    public function test_an_owner_can_delete_a_spare_empty_organization(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create(['user_id' => $user->id]);
        $spare = Organization::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/organizations/{$spare->org_id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('organizations', ['id' => $spare->id]);
    }

    public function test_the_last_organization_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/organizations/{$organization->org_id}")
            ->assertStatus(422)
            ->assertJson(['error' => 'Cannot delete the last organization']);

        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'deleted_at' => null]);
    }

    /**
     * AWS accounts must be removed first so their cross-account IAM roles are
     * cleaned up deliberately rather than orphaned.
     */
    public function test_an_organization_with_aws_accounts_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create(['user_id' => $user->id]);
        $organization = Organization::factory()->create(['user_id' => $user->id]);
        AwsAccount::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->deleteJson("/api/organizations/{$organization->org_id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'deleted_at' => null]);
    }

    public function test_an_administrator_cannot_delete_an_organization(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create(['user_id' => $user->id]);
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'administrator',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/organizations/{$organization->org_id}")
            ->assertStatus(403);
    }
}
