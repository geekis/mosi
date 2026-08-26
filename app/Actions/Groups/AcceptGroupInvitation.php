<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupInvitation;

/**
 * Accept an invitation: add the member and clear the invitation. Mirrors
 * ApproveJoinRequest, including pulling the new member into an existing group chat.
 */
final class AcceptGroupInvitation
{
    public function __invoke(GroupInvitation $invitation): void
    {
        $group = Group::query()->findOrFail($invitation->group_id);

        $group->members()->syncWithoutDetaching([
            $invitation->user_id => ['role' => GroupRole::Member->value],
        ]);

        $group->conversation()->first()?->participants()->syncWithoutDetaching([$invitation->user_id]);

        $invitation->delete();
    }
}
