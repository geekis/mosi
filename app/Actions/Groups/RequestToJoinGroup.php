<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\User;
use DomainException;

/**
 * Request to join a closed group. Owners/moderators approve via ApproveJoinRequest.
 */
final class RequestToJoinGroup
{
    public function __invoke(User $user, Group $group): GroupJoinRequest
    {
        if ($group->visibility !== GroupVisibility::Closed) {
            throw new DomainException('Only closed groups accept join requests.');
        }

        if ($group->hasMember($user)) {
            throw new DomainException('The user is already a member.');
        }

        return GroupJoinRequest::firstOrCreate([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);
    }
}
