<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;

/**
 * Authorisation for groups. `view` is the model-level counterpart of
 * {@see Group::scopeVisibleTo()} — a secret group is invisible to non-members.
 */
class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // listing is scoped by visibleTo()
    }

    public function view(User $user, Group $group): bool
    {
        return ! $group->visibility->isSecret() || $group->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true; // any active staff member may start an interest or project group
    }

    public function update(User $user, Group $group): bool
    {
        return $user->hasAnyRoleName('super-admin', 'comms')
            || $this->moderates($user, $group);
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->hasAnyRoleName('super-admin', 'comms')
            || $this->owns($user, $group);
    }

    private function moderates(User $user, Group $group): bool
    {
        return $group->members()
            ->whereKey($user->id)
            ->wherePivotIn('role', [GroupRole::Owner->value, GroupRole::Moderator->value])
            ->exists();
    }

    private function owns(User $user, Group $group): bool
    {
        return $group->members()
            ->whereKey($user->id)
            ->wherePivot('role', GroupRole::Owner->value)
            ->exists();
    }
}
