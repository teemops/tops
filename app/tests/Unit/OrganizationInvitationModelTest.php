<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationInvitationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_timestamps_are_cast_to_dates(): void
    {
        $invitation = OrganizationInvitation::factory()->accepted()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invitation->expires_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invitation->accepted_at);
    }

    public function test_relationships_resolve(): void
    {
        $inviter = User::factory()->create();
        $organization = Organization::factory()->create();
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $inviter->id,
        ]);

        $this->assertTrue($invitation->organization->is($organization));
        $this->assertTrue($invitation->invitedBy->is($inviter));
    }

    public function test_is_expired_reflects_the_expiry_timestamp(): void
    {
        $this->assertFalse(OrganizationInvitation::factory()->create()->isExpired());
        $this->assertTrue(OrganizationInvitation::factory()->expired()->create()->isExpired());
    }

    public function test_is_accepted_reflects_the_accepted_timestamp(): void
    {
        $this->assertFalse(OrganizationInvitation::factory()->create()->isAccepted());
        $this->assertTrue(OrganizationInvitation::factory()->accepted()->create()->isAccepted());
    }

    public function test_accept_creates_a_member_without_a_role_and_stamps_the_invitation(): void
    {
        $organization = Organization::factory()->create();
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $user = User::factory()->create();

        $member = $invitation->accept($user);

        $this->assertInstanceOf(OrganizationMember::class, $member);
        $this->assertEquals($organization->id, $member->organization_id);
        $this->assertEquals($user->id, $member->user_id);
        $this->assertNull($member->role, 'A newly accepted member has no role until one is assigned.');

        $this->assertTrue($invitation->fresh()->isAccepted());
    }
}
