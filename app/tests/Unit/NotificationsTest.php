<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\InviteMemberNotification;
use App\Notifications\MemberJoinedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_invite_notification_is_delivered_by_mail(): void
    {
        $notification = new InviteMemberNotification(OrganizationInvitation::factory()->create());

        $this->assertEquals(['mail'], $notification->via(new \stdClass()));
    }

    public function test_the_invite_mail_links_to_the_accept_url(): void
    {
        $inviter = User::factory()->create(['name' => 'Ada Lovelace']);
        $organization = Organization::factory()->create(['name' => 'Acme Corp']);
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by' => $inviter->id,
            'token' => 'invite-token-123',
        ]);

        $mail = (new InviteMemberNotification($invitation))->toMail(new \stdClass());

        $this->assertEquals("You've been invited to join Acme Corp", $mail->subject);
        $this->assertEquals('Accept Invitation', $mail->actionText);
        $this->assertEquals(url('/organizations/invitations/invite-token-123/accept'), $mail->actionUrl);
        $this->assertStringContainsString('Ada Lovelace', implode(' ', $mail->introLines));
        $this->assertStringContainsString('Acme Corp', implode(' ', $mail->introLines));
    }

    public function test_the_invite_array_payload_identifies_the_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Acme Corp']);
        $invitation = OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);

        $payload = (new InviteMemberNotification($invitation))->toArray(new \stdClass());

        $this->assertEquals($invitation->id, $payload['invitation_id']);
        $this->assertEquals($organization->id, $payload['organization_id']);
        $this->assertEquals('Acme Corp', $payload['organization_name']);
    }

    public function test_the_member_joined_notification_is_delivered_by_mail(): void
    {
        $notification = new MemberJoinedNotification(
            Organization::factory()->create(),
            User::factory()->create()
        );

        $this->assertEquals(['mail'], $notification->via(new \stdClass()));
    }

    public function test_the_member_joined_mail_links_to_organization_settings(): void
    {
        $owner = User::factory()->create(['name' => 'Grace Hopper']);
        $organization = Organization::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Acme Corp',
        ]);
        $newMember = User::factory()->create([
            'name' => 'Alan Turing',
            'email' => 'alan@example.com',
        ]);

        $mail = (new MemberJoinedNotification($organization, $newMember))->toMail($owner);

        $this->assertEquals('Alan Turing joined Acme Corp', $mail->subject);
        $this->assertEquals('Hello Grace Hopper!', $mail->greeting);
        $this->assertEquals('Manage Team Members', $mail->actionText);
        $this->assertEquals(url("/organizations/{$organization->org_id}/settings"), $mail->actionUrl);
        $this->assertStringContainsString('alan@example.com', implode(' ', $mail->introLines));
    }

    public function test_the_member_joined_array_payload_identifies_the_new_member(): void
    {
        $organization = Organization::factory()->create(['name' => 'Acme Corp']);
        $newMember = User::factory()->create([
            'name' => 'Alan Turing',
            'email' => 'alan@example.com',
        ]);

        $payload = (new MemberJoinedNotification($organization, $newMember))->toArray($organization->owner);

        $this->assertEquals($organization->id, $payload['organization_id']);
        $this->assertEquals('Acme Corp', $payload['organization_name']);
        $this->assertEquals($newMember->id, $payload['new_member_id']);
        $this->assertEquals('Alan Turing', $payload['new_member_name']);
        $this->assertEquals('alan@example.com', $payload['new_member_email']);
    }
}
