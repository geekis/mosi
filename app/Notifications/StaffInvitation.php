<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

/**
 * Invite a staff member to set their password. Sent to personal addresses, so the
 * content is deliberately minimal — no kennitala, job details or org structure.
 */
class StaffInvitation extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->given_name : null;
        $email = $notifiable instanceof User ? $notifiable->email : null;

        // Render in the recipient's locale, not the queue worker's (localisation rule).
        App::setLocale(($notifiable instanceof User ? $notifiable->locale : null) ?? config('app.fallback_locale'));

        $url = route('invitation.accept', [
            'token' => $this->token,
            'email' => $email,
        ]);

        return (new MailMessage)
            ->subject(__('staff.invitation.subject'))
            ->greeting($name !== null ? __('staff.invitation.greeting', ['name' => $name]) : __('staff.invitation.greeting_generic'))
            ->line(__('staff.invitation.intro'))
            ->action(__('staff.invitation.action'), $url)
            ->line(__('staff.invitation.expiry', ['days' => 14]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
