<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\User;

/**
 * Record or change a person's RSVP. One response per person per event; responding again
 * overwrites the previous status. Passing null removes the response entirely.
 */
final class RespondToEvent
{
    public function __invoke(User $user, Event $event, ?RsvpStatus $status): void
    {
        if ($status === null) {
            $event->attendees()->detach($user->id);

            return;
        }

        $event->attendees()->syncWithoutDetaching([$user->id => ['status' => $status->value]]);
    }
}
