<?php

namespace Tests\Unit;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_org_id_is_generated_on_creation(): void
    {
        $organization = Organization::factory()->create();

        $this->assertNotEmpty($organization->org_id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $organization->org_id
        );
    }

    public function test_an_explicit_org_id_is_preserved(): void
    {
        $organization = Organization::factory()->create(['org_id' => 'my-fixed-org-id']);

        $this->assertEquals('my-fixed-org-id', $organization->org_id);
    }

    public function test_is_default_is_cast_to_a_boolean(): void
    {
        $organization = Organization::factory()->default()->create();

        $this->assertTrue($organization->is_default);
        $this->assertIsBool($organization->fresh()->is_default);
    }

    public function test_the_organization_is_soft_deleted(): void
    {
        $organization = Organization::factory()->create();

        $organization->delete();

        $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
        $this->assertNotNull(Organization::withTrashed()->find($organization->id));
    }

    public function test_relationships_resolve(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $owner->id]);
        $awsAccount = AwsAccount::factory()->create(['organization_id' => $organization->id]);
        Scan::factory()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);

        $this->assertTrue($organization->user->is($owner));
        $this->assertTrue($organization->owner->is($owner));
        $this->assertCount(1, $organization->awsAccounts);
        $this->assertCount(1, $organization->scans);
        $this->assertCount(1, $organization->members);
        $this->assertCount(1, $organization->invitations);
    }

    public function test_is_owner_identifies_the_owning_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($organization->isOwner($owner));
        $this->assertFalse($organization->isOwner($other));
    }

    public function test_get_member_role_returns_owner_for_the_owner(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $owner->id]);

        $this->assertEquals('owner', $organization->getMemberRole($owner));
    }

    public function test_get_member_role_returns_the_member_role(): void
    {
        $member = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => 'administrator',
        ]);

        $this->assertEquals('administrator', $organization->getMemberRole($member));
    }

    public function test_get_member_role_returns_null_for_a_stranger(): void
    {
        $organization = Organization::factory()->create();

        $this->assertNull($organization->getMemberRole(User::factory()->create()));
    }

    public function test_transfer_ownership_promotes_an_existing_member(): void
    {
        $oldOwner = User::factory()->create();
        $newOwner = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $oldOwner->id]);

        $oldOwnerMember = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $oldOwner->id,
            'role' => 'owner',
        ]);
        $newOwnerMember = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $newOwner->id,
            'role' => 'administrator',
        ]);

        $organization->transferOwnership($newOwner);

        $this->assertEquals($newOwner->id, $organization->fresh()->user_id);
        $this->assertEquals('owner', $newOwnerMember->fresh()->role);
        $this->assertNull($oldOwnerMember->fresh()->role);
    }

    public function test_transfer_ownership_creates_a_member_record_for_an_outsider(): void
    {
        $oldOwner = User::factory()->create();
        $newOwner = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $oldOwner->id]);

        $organization->transferOwnership($newOwner);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $newOwner->id,
            'role' => 'owner',
        ]);
        $this->assertEquals($newOwner->id, $organization->fresh()->user_id);
    }
}
