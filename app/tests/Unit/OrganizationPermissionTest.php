<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\OrganizationPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationPermissionTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationPermission $permission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permission = new OrganizationPermission();
    }

    /**
     * Create an org owned by someone else, with $user attached in the given role.
     * Passing null attaches a member record with no role yet.
     */
    private function orgWithMember(User $user, ?string $role): Organization
    {
        $organization = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $organization;
    }

    public function test_the_organization_owner_always_resolves_to_the_owner_role(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $this->assertEquals('owner', $this->permission->getUserRole($user, $organization));
    }

    /**
     * Owners predating the members table get a member record backfilled on lookup.
     */
    public function test_looking_up_the_owner_backfills_a_missing_member_record(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $this->assertDatabaseMissing('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $this->permission->getUserRole($user, $organization);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    /**
     * A stale member row for the owner is corrected to 'owner'.
     */
    public function test_looking_up_the_owner_corrects_a_mismatched_member_role(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);
        $member = OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'viewer',
        ]);

        $this->assertEquals('owner', $this->permission->getUserRole($user, $organization));
        $this->assertEquals('owner', $member->fresh()->role);
    }

    public function test_a_member_resolves_to_their_assigned_role(): void
    {
        $user = User::factory()->create();
        $organization = $this->orgWithMember($user, 'auditor');

        $this->assertEquals('auditor', $this->permission->getUserRole($user, $organization));
    }

    public function test_a_stranger_has_no_role(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->assertNull($this->permission->getUserRole($user, $organization));
        $this->assertFalse($this->permission->hasRole($user, $organization));
    }

    /**
     * hasNoRole distinguishes "invited but unassigned" from "not a member".
     */
    public function test_has_no_role_only_matches_members_awaiting_a_role(): void
    {
        $pending = User::factory()->create();
        $pendingOrg = $this->orgWithMember($pending, null);
        $this->assertTrue($this->permission->hasNoRole($pending, $pendingOrg));

        $assigned = User::factory()->create();
        $assignedOrg = $this->orgWithMember($assigned, 'viewer');
        $this->assertFalse($this->permission->hasNoRole($assigned, $assignedOrg));

        $stranger = User::factory()->create();
        $this->assertFalse($this->permission->hasNoRole($stranger, Organization::factory()->create()));
    }

    /**
     * The full permission matrix, one row per role.
     */
    #[DataProvider('rolePermissionProvider')]
    public function test_permissions_for_each_role(?string $role, array $expected): void
    {
        $user = User::factory()->create();
        $organization = $role === 'owner'
            ? Organization::factory()->create(['user_id' => $user->id])
            : $this->orgWithMember($user, $role);

        foreach ($expected as $capability => $allowed) {
            $this->assertSame(
                $allowed,
                $this->permission->{$capability}($user, $organization),
                "{$capability} for role ".var_export($role, true)
            );
        }
    }

    public static function rolePermissionProvider(): array
    {
        return [
            'owner' => ['owner', [
                'canManageSettings' => true,
                'canInviteMembers' => true,
                'canManageMembers' => true,
                'canRunScans' => true,
                'canViewAwsAccounts' => true,
                'canAddAwsAccounts' => true,
                'canDeleteAwsAccounts' => true,
                'canViewScans' => true,
                'canViewFindings' => true,
                'canViewInsights' => true,
                'canTransferOwnership' => true,
                'canDeleteOrganization' => true,
                'canListMembers' => true,
            ]],
            'administrator' => ['administrator', [
                'canManageSettings' => true,
                'canInviteMembers' => true,
                'canManageMembers' => true,
                'canRunScans' => true,
                'canViewAwsAccounts' => true,
                'canAddAwsAccounts' => true,
                'canDeleteAwsAccounts' => true,
                'canViewScans' => true,
                'canViewFindings' => true,
                'canViewInsights' => true,
                'canTransferOwnership' => false,
                'canDeleteOrganization' => false,
                'canListMembers' => true,
            ]],
            'auditor' => ['auditor', [
                'canManageSettings' => false,
                'canInviteMembers' => false,
                'canManageMembers' => false,
                'canRunScans' => true,
                'canViewAwsAccounts' => true,
                'canAddAwsAccounts' => false,
                'canDeleteAwsAccounts' => false,
                'canViewScans' => true,
                'canViewFindings' => true,
                'canViewInsights' => true,
                'canTransferOwnership' => false,
                'canDeleteOrganization' => false,
                'canListMembers' => false,
            ]],
            'viewer' => ['viewer', [
                'canManageSettings' => false,
                'canInviteMembers' => false,
                'canManageMembers' => false,
                'canRunScans' => false,
                'canViewAwsAccounts' => false,
                'canAddAwsAccounts' => false,
                'canDeleteAwsAccounts' => false,
                'canViewScans' => false,
                'canViewFindings' => true,
                'canViewInsights' => true,
                'canTransferOwnership' => false,
                'canDeleteOrganization' => false,
                'canListMembers' => false,
            ]],
            'member awaiting a role' => [null, [
                'canManageSettings' => false,
                'canInviteMembers' => false,
                'canManageMembers' => false,
                'canRunScans' => false,
                'canViewAwsAccounts' => false,
                'canAddAwsAccounts' => false,
                'canDeleteAwsAccounts' => false,
                'canViewScans' => false,
                'canViewFindings' => false,
                'canViewInsights' => false,
                'canTransferOwnership' => false,
                'canDeleteOrganization' => false,
                'canListMembers' => false,
            ]],
        ];
    }
}
