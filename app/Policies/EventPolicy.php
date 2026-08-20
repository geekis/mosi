<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;

/**
 * Authorisation for events. `view` mirrors {@see Event::scopeVisibleTo()} — an event in
 * a secret group is invisible to non-members. Editing is limited to the creator, comms,
 * and super-admins.
 */
class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        if ($event->group_id === null) {
            return true;
        }

        return Group::query()->whereKey($event->group_id)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return $event->created_by === $user->id || $user->hasAnyRoleName('comms', 'super-admin');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
