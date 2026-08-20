<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a user that someone started following them. Database-only (low urgency).
 */
class NewFollower extends Notification
{
    use Queueable;

    public function __construct(public User $follower) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->wantsNotification('new_follower')) {
            return [];
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => 'new_follower',
            'params' => ['name' => $this->follower->display_name],
            'url' => $this->follower->external_id ? route('people.show', $this->follower) : route('dashboard'),
            'actor_id' => $this->follower->id,
        ];
    }
}
