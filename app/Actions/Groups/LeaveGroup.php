<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;

final class LeaveGroup
{
    public function __invoke(User $user, Group $group): void
    {
        $group->members()->detach($user->id);

        // Leaving the group also removes you from its chat.
        $group->conversation()->first()?->participants()->detach($user->id);
    }
}
