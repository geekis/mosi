<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupJoinRequest;

/**
 * Approve a pending join request: add the member and clear the request.
 */
final class ApproveJoinRequest
{
    public function __invoke(GroupJoinRequest $request): void
    {
        $group = Group::query()->findOrFail($request->group_id);

        $group->members()->syncWithoutDetaching([
            $request->user_id => ['role' => GroupRole::Member->value],
        ]);

        $request->delete();
    }
}
