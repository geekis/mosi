<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\User;
use App\Notifications\NewGroupPost;
use Illuminate\Support\Facades\Notification;

/**
 * Notify the members of a group that a post was published in it — never the author, and
 * only for a post that is actually live (not a draft or a future-scheduled one). Sending
 * only to members keeps a secret group's activity from leaking. Each recipient's own
 * `group_post` preference is honoured in {@see NewGroupPost::via()}.
 */
final class NotifyGroupPost
{
    public function __invoke(Post $post, User $author): void
    {
        if ($post->group_id === null) {
            return;
        }

        if ($post->published_at === null || $post->published_at->isFuture()) {
            return;
        }

        $post->loadMissing('group');

        if ($post->group === null) {
            return;
        }

        $recipients = $post->group->members()
            ->where('users.is_active', true)
            ->where('users.id', '!=', $author->id)
            ->get();

        Notification::send($recipients, new NewGroupPost($post, $author));
    }
}
