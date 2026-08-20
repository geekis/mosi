<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use DomainException;

/**
 * Create an event. A group event requires the creator to be a member; a null group_id
 * is an organisation-wide event anyone may see.
 *
 * @phpstan-type EventData array{title: array<string, string>|string, description?: array<string, string>|string|null, location?: string|null, starts_at: mixed, ends_at?: mixed, is_all_day?: bool, group_id?: int|null}
 */
final class CreateEvent
{
    /**
     * @param  EventData  $data
     */
    public function __invoke(User $creator, array $data): Event
    {
        $groupId = $data['group_id'] ?? null;

        if ($groupId !== null) {
            $group = Group::query()->findOrFail($groupId);

            if (! $group->hasMember($creator)) {
                throw new DomainException('You must be a member of the group to create an event in it.');
            }
        }

        return Event::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'is_all_day' => $data['is_all_day'] ?? false,
            'group_id' => $groupId,
            'created_by' => $creator->id,
        ]);
    }
}
