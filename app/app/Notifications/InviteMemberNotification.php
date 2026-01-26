<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation as OrganizationInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteMemberNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public OrganizationInvitationModel $invitation
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = url("/organizations/invitations/{$this->invitation->token}/accept");
        
        return (new MailMessage)
            ->subject("You've been invited to join {$this->invitation->organization->name}")
            ->greeting("Hello!")
            ->line("You have been invited to join the organization **{$this->invitation->organization->name}** on Teemops.")
            ->line("{$this->invitation->invitedBy->name} has invited you to collaborate on their cloud security platform.")
            ->line("Click the button below to accept the invitation. If you don't have an account yet, you'll be able to sign up first.")
            ->action('Accept Invitation', $acceptUrl)
            ->line("This invitation will expire in 24 hours.")
            ->line("If you did not expect this invitation, you can safely ignore this email.");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_id' => $this->invitation->organization_id,
            'organization_name' => $this->invitation->organization->name,
        ];
    }
}
