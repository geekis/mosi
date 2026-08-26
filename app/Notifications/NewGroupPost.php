<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a group member that someone posted in one of their groups. Database-only and
 * gated by the `group_post` preference. Only sent to members (in NotifyGroupPost), so it
 * never leaks a secret group. The stored `group_id` drives the per-group unread badge in
 * the sidebar and is what a group visit marks read.
 */
class NewGroupPost extends Notification
{
    use Queueable;

    public function __construct(
        public Post $post,
        public User $author,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->wantsNotification('group_post')) {
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
            'key' => 'group_post',
            'params' => [
                'name' => $this->author->display_name,
                'group' => $this->post->group?->name,
            ],
            'url' => $this->post->group === null
                ? route('dashboard')
                : route('groups.show', $this->post->group),
            'actor_id' => $this->author->id,
            'group_id' => $this->post->group_id,
            'post_id' => $this->post->id,
        ];
    }
}
