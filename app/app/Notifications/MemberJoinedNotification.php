<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberJoinedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Organization $organization,
        public User $newMember
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
        $settingsUrl = url("/organizations/{$this->organization->org_id}/settings");
        
        return (new MailMessage)
            ->subject("{$this->newMember->name} joined {$this->organization->name}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("**{$this->newMember->name}** ({$this->newMember->email}) has accepted your invitation and joined the organization **{$this->organization->name}**.")
            ->line("They are now a member but don't have a role assigned yet. You can assign them a role in the organization settings.")
            ->action('Manage Team Members', $settingsUrl)
            ->line("Thank you for using Teemops!");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'new_member_id' => $this->newMember->id,
            'new_member_name' => $this->newMember->name,
            'new_member_email' => $this->newMember->email,
        ];
    }
}
