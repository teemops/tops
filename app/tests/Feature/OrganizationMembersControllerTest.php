<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\InviteMemberNotification;
use App\Notifications\MemberJoinedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationMembersControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create();
        $this->organization = Organization::factory()->create(['user_id' => $this->owner->id]);
    }

    private function addMember(?string $role, ?User $user = null): OrganizationMember
    {
        return OrganizationMember::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => ($user ?? User::factory()->create())->id,
            'role' => $role,
        ]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/organizations/{$this->organization->org_id}/members".$suffix;
    }

    // ---------------------------------------------------------------- listing

    public function test_an_owner_can_list_members(): void
    {
        $member = $this->addMember('auditor');

        $response = $this->actingAs($this->owner)->getJson($this->url());

        $response->assertOk();
        $members = collect($response->json('members'));

        $this->assertNotNull($members->firstWhere('user_id', $member->user_id));
        $this->assertEquals('owner', $members->firstWhere('user_id', $this->owner->id)['role']);
    }

    /**
     * Listing members is an administrative view; auditors and viewers are shut out.
     */
    public function test_an_auditor_cannot_list_members(): void
    {
        $auditor = User::factory()->create();
        $this->addMember('auditor', $auditor);

        $this->actingAs($auditor)->getJson($this->url())->assertStatus(403);
    }

    public function test_an_administrator_can_list_members(): void
    {
        $administrator = User::factory()->create();
        $this->addMember('administrator', $administrator);

        $this->actingAs($administrator)->getJson($this->url())->assertOk();
    }

    // ------------------------------------------------------------- inviting

    public function test_an_owner_can_invite_a_member(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson($this->url('/invite'), ['email' => 'new@example.com']);

        $response->assertStatus(201)->assertJson(['email' => 'new@example.com']);

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $this->organization->id,
            'email' => 'new@example.com',
            'invited_by' => $this->owner->id,
        ]);
        Notification::assertSentOnDemand(InviteMemberNotification::class);
    }

    public function test_an_invitation_expires_after_24_hours(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson($this->url('/invite'), ['email' => 'new@example.com']);

        $invitation = OrganizationInvitation::findOrFail($response->json('id'));

        $this->assertEqualsWithDelta(
            now()->addHours(24)->timestamp,
            $invitation->expires_at->timestamp,
            5
        );
    }

    public function test_an_auditor_cannot_invite(): void
    {
        $auditor = User::factory()->create();
        $this->addMember('auditor', $auditor);

        $this->actingAs($auditor)
            ->postJson($this->url('/invite'), ['email' => 'new@example.com'])
            ->assertStatus(403);
    }

    public function test_inviting_an_existing_member_is_rejected(): void
    {
        $existing = User::factory()->create(['email' => 'member@example.com']);
        $this->addMember('viewer', $existing);

        $this->actingAs($this->owner)
            ->postJson($this->url('/invite'), ['email' => 'member@example.com'])
            ->assertStatus(422)
            ->assertJson(['error' => 'User is already a member of this organization']);
    }

    /**
     * The owner is caught by the "already a member" check: the permission lookup
     * that gates this endpoint backfills an owner member record first.
     */
    public function test_inviting_the_owner_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->postJson($this->url('/invite'), ['email' => $this->owner->email])
            ->assertStatus(422)
            ->assertJson(['error' => 'User is already a member of this organization']);

        $this->assertDatabaseMissing('organization_invitations', ['email' => $this->owner->email]);
    }

    public function test_inviting_the_same_email_twice_is_rejected(): void
    {
        OrganizationInvitation::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'pending@example.com',
        ]);

        $this->actingAs($this->owner)
            ->postJson($this->url('/invite'), ['email' => 'pending@example.com'])
            ->assertStatus(422)
            ->assertJson(['error' => 'An invitation has already been sent to this email address']);
    }

    /**
     * A lapsed invitation should not block a fresh one.
     */
    public function test_an_expired_invitation_can_be_reissued(): void
    {
        OrganizationInvitation::factory()->expired()->create([
            'organization_id' => $this->organization->id,
            'email' => 'lapsed@example.com',
        ]);

        $this->actingAs($this->owner)
            ->postJson($this->url('/invite'), ['email' => 'lapsed@example.com'])
            ->assertStatus(201);
    }

    // --------------------------------------------------------- role updates

    public function test_an_owner_can_change_a_members_role(): void
    {
        $member = $this->addMember('viewer');

        $this->actingAs($this->owner)
            ->putJson($this->url("/{$member->id}"), ['role' => 'auditor'])
            ->assertOk()
            ->assertJson(['role' => 'auditor']);

        $this->assertEquals('auditor', $member->fresh()->role);
    }

    public function test_assigning_the_owner_role_transfers_ownership(): void
    {
        $member = $this->addMember('administrator');

        $this->actingAs($this->owner)
            ->putJson($this->url("/{$member->id}"), ['role' => 'owner'])
            ->assertOk()
            ->assertJson(['message' => 'Ownership transferred successfully']);

        $this->assertEquals($member->user_id, $this->organization->fresh()->user_id);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $member = $this->addMember('viewer');

        $this->actingAs($this->owner)
            ->putJson($this->url("/{$member->id}"), ['role' => 'superuser'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_a_member_of_another_organization_is_not_found(): void
    {
        $foreign = OrganizationMember::factory()->create();

        $this->actingAs($this->owner)
            ->putJson($this->url("/{$foreign->id}"), ['role' => 'viewer'])
            ->assertStatus(404);
    }

    public function test_an_auditor_cannot_change_roles(): void
    {
        $auditor = User::factory()->create();
        $this->addMember('auditor', $auditor);
        $member = $this->addMember('viewer');

        $this->actingAs($auditor)
            ->putJson($this->url("/{$member->id}"), ['role' => 'administrator'])
            ->assertStatus(403);
    }

    // ------------------------------------------------------------- removal

    public function test_an_owner_can_remove_a_member(): void
    {
        $member = $this->addMember('viewer');

        $this->actingAs($this->owner)
            ->deleteJson($this->url("/{$member->id}"))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('organization_members', ['id' => $member->id]);
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $ownerMember = $this->addMember('owner', $this->owner);

        $this->actingAs($this->owner)
            ->deleteJson($this->url("/{$ownerMember->id}"))
            ->assertStatus(422)
            ->assertJson(['error' => 'Cannot remove owner. Transfer ownership first.']);
    }

    public function test_the_last_administrator_cannot_be_removed(): void
    {
        $administrator = $this->addMember('administrator');

        $this->actingAs($this->owner)
            ->deleteJson($this->url("/{$administrator->id}"))
            ->assertStatus(422)
            ->assertJson(['error' => 'Cannot remove the last administrator']);
    }

    public function test_an_administrator_can_be_removed_when_another_remains(): void
    {
        $first = $this->addMember('administrator');
        $this->addMember('administrator');

        $this->actingAs($this->owner)
            ->deleteJson($this->url("/{$first->id}"))
            ->assertOk();
    }

    // ---------------------------------------------------------- invitations

    public function test_an_owner_can_list_pending_invitations(): void
    {
        OrganizationInvitation::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'pending@example.com',
            'invited_by' => $this->owner->id,
        ]);
        OrganizationInvitation::factory()->expired()->create([
            'organization_id' => $this->organization->id,
            'invited_by' => $this->owner->id,
        ]);
        OrganizationInvitation::factory()->accepted()->create([
            'organization_id' => $this->organization->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/organizations/{$this->organization->org_id}/invitations");

        $response->assertOk();
        $this->assertCount(1, $response->json('invitations'));
        $this->assertEquals('pending@example.com', $response->json('invitations.0.email'));
    }

    public function test_an_owner_can_cancel_an_invitation(): void
    {
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/organizations/{$this->organization->org_id}/invitations/{$invitation->id}")
            ->assertOk();

        $this->assertDatabaseMissing('organization_invitations', ['id' => $invitation->id]);
    }

    public function test_an_accepted_invitation_cannot_be_cancelled(): void
    {
        $invitation = OrganizationInvitation::factory()->accepted()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/organizations/{$this->organization->org_id}/invitations/{$invitation->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('organization_invitations', ['id' => $invitation->id]);
    }

    public function test_an_invitation_from_another_organization_is_not_found(): void
    {
        $foreign = OrganizationInvitation::factory()->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/organizations/{$this->organization->org_id}/invitations/{$foreign->id}")
            ->assertStatus(404);
    }

    // ---------------------------------------------------- accept invitation

    public function test_an_invitee_can_accept_their_invitation(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'invitee@example.com',
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($invitee)
            ->postJson("/api/organizations/invitations/{$invitation->token}/accept");

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNull($response->json('member.role'), 'A new member has no role until assigned.');

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $this->organization->id,
            'user_id' => $invitee->id,
        ]);
        $this->assertTrue($invitation->fresh()->isAccepted());
        Notification::assertSentTo($this->owner, MemberJoinedNotification::class);
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = OrganizationInvitation::factory()->expired()->create([
            'organization_id' => $this->organization->id,
            'email' => 'invitee@example.com',
        ]);

        $this->actingAs($invitee)
            ->postJson("/api/organizations/invitations/{$invitation->token}/accept")
            ->assertStatus(422)
            ->assertJson(['error' => 'This invitation has expired']);
    }

    public function test_an_invitation_cannot_be_accepted_twice(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = OrganizationInvitation::factory()->accepted()->create([
            'organization_id' => $this->organization->id,
            'email' => 'invitee@example.com',
        ]);

        $this->actingAs($invitee)
            ->postJson("/api/organizations/invitations/{$invitation->token}/accept")
            ->assertStatus(422)
            ->assertJson(['error' => 'This invitation has already been accepted']);
    }

    /**
     * An invitation is bound to the address it was sent to.
     */
    public function test_an_invitation_cannot_be_accepted_by_a_different_account(): void
    {
        $someoneElse = User::factory()->create(['email' => 'other@example.com']);
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'invitee@example.com',
        ]);

        $this->actingAs($someoneElse)
            ->postJson("/api/organizations/invitations/{$invitation->token}/accept")
            ->assertStatus(403);

        $this->assertFalse($invitation->fresh()->isAccepted());
    }

    public function test_an_existing_member_cannot_accept_an_invitation(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $this->addMember('viewer', $invitee);
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'invitee@example.com',
        ]);

        $this->actingAs($invitee)
            ->postJson("/api/organizations/invitations/{$invitation->token}/accept")
            ->assertStatus(422)
            ->assertJson(['error' => 'You are already a member of this organization']);
    }

    public function test_an_unknown_invitation_token_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/organizations/invitations/no-such-token/accept')
            ->assertStatus(404);
    }

    // ------------------------------------------------- transfer ownership

    public function test_an_owner_can_transfer_ownership_to_a_member(): void
    {
        $member = $this->addMember('administrator');

        $response = $this->actingAs($this->owner)
            ->postJson("/api/organizations/{$this->organization->org_id}/transfer-ownership", [
                'user_id' => $member->user_id,
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals($member->user_id, $this->organization->fresh()->user_id);
        $this->assertEquals('owner', $member->fresh()->role);
    }

    public function test_ownership_cannot_be_transferred_to_a_non_member(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($this->owner)
            ->postJson("/api/organizations/{$this->organization->org_id}/transfer-ownership", [
                'user_id' => $outsider->id,
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'User must be a member of the organization to become owner']);
    }

    public function test_ownership_cannot_be_transferred_to_the_current_owner(): void
    {
        $this->addMember('owner', $this->owner);

        $this->actingAs($this->owner)
            ->postJson("/api/organizations/{$this->organization->org_id}/transfer-ownership", [
                'user_id' => $this->owner->id,
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'You are already the owner of this organization']);
    }

    public function test_an_administrator_cannot_transfer_ownership(): void
    {
        $administrator = User::factory()->create();
        $this->addMember('administrator', $administrator);
        $target = $this->addMember('viewer');

        $this->actingAs($administrator)
            ->postJson("/api/organizations/{$this->organization->org_id}/transfer-ownership", [
                'user_id' => $target->user_id,
            ])
            ->assertStatus(403);
    }

    public function test_transferring_ownership_validates_the_target_user(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/organizations/{$this->organization->org_id}/transfer-ownership", [
                'user_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }
}
