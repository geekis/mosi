<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use App\Notifications\NewGroupPost;

/**
 * Mark the user's unread new-post notifications for a group as read — called when they
 * open the group, so the sidebar badge and the bell clear together.
 */
final class MarkGroupNotificationsRead
{
    public function __invoke(User $user, Group $group): void
    {
        $user->unreadNotifications()
            ->where('type', NewGroupPost::class)
            ->where('data->group_id', $group->id)
            ->update(['read_at' => now()]);
    }
}
