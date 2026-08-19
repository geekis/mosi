<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

/**
 * Sent to administrators when the staff sync aborts because the payload shrank by more
 * than 15% — a likely partial API failure that must not deactivate the workforce.
 */
class StaffSyncCircuitBreakerTripped extends Notification
{
    use Queueable;

    public function __construct(
        public int $previousTotal,
        public int $currentTotal,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Render in the recipient's locale, not the queue worker's (localisation rule).
        App::setLocale($notifiable->locale ?? config('app.fallback_locale'));

        return (new MailMessage)
            ->subject(__('staff.sync_aborted.subject'))
            ->line(__('staff.sync_aborted.body', [
                'previous' => $this->previousTotal,
                'current' => $this->currentTotal,
            ]))
            ->line(__('staff.sync_aborted.action'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'previous_total' => $this->previousTotal,
            'current_total' => $this->currentTotal,
        ];
    }
}
