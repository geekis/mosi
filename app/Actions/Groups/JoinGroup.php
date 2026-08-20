<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Enums\GroupRole;
use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\User;
use DomainException;

/**
 * Join an open group directly. Closed groups go through RequestToJoinGroup; secret
 * groups are invite-only. This is the single place a self-service join can happen.
 */
final class JoinGroup
{
    public function __invoke(User $user, Group $group): void
    {
        if ($group->visibility !== GroupVisibility::Open) {
            throw new DomainException('Only open groups can be joined directly.');
        }

        $group->members()->syncWithoutDetaching([
            $user->id => ['role' => GroupRole::Member->value],
        ]);
    }
}
