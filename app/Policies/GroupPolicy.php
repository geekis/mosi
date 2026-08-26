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

    /**
     * Members-only content — the group's events, files and media gallery. Unlike
     * {@see view()} (which lets anyone see a non-secret group's page), this is limited to
     * actual members, so an open group's files never leak to non-members (.ai/rules/auth.md).
     */
    public function viewMemberContent(User $user, Group $group): bool
    {
        return $group->hasMember($user);
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

    /**
     * Approve or decline requests to join a closed group. Same set as {@see update()}:
     * owners, moderators, comms and super-admins.
     */
    public function manageJoinRequests(User $user, Group $group): bool
    {
        return $this->update($user, $group);
    }

    /**
     * Invite a colleague into the group. Same set as {@see update()} — an invitation is
     * the only path into a secret group and bypasses the closed-group request queue, so
     * it stays with owners/moderators rather than any member.
     */
    public function invite(User $user, Group $group): bool
    {
        return $this->update($user, $group);
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
