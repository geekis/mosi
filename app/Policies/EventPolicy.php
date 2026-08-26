<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;

/**
 * Authorisation for events. `view` mirrors {@see Event::scopeVisibleTo()} — a group event
 * is visible only to members of that group; an organisation-wide event (no group) is
 * visible to everyone. Editing is limited to the creator, comms, and super-admins.
 */
class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        if ($event->group_id === null) {
            return true;
        }

        return Group::query()->whereKey($event->group_id)->first()?->hasMember($user) ?? false;
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
