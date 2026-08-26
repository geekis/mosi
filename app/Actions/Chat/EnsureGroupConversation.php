<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\Group;

/**
 * Get a group's dedicated chat, creating it on first use, and make sure every current
 * member is a participant. Idempotent: a group always has exactly one group chat, and
 * "everybody in the group" is kept in sync — members who joined are added, members who
 * left are removed. Existing participants keep their read state (syncWithoutDetaching).
 */
final class EnsureGroupConversation
{
    public function __invoke(Group $group): Conversation
    {
        $conversation = $group->conversation()->first();

        if ($conversation === null) {
            $conversation = Conversation::create([
                'type' => ConversationType::Group,
                'group_id' => $group->id,
                'title' => $group->name,
                'created_by' => $group->created_by,
            ]);
        } elseif ($conversation->title !== $group->name) {
            // Keep the denormalised title in step with a renamed group.
            $conversation->update(['title' => $group->name]);
        }

        $this->syncParticipants($group, $conversation);

        return $conversation;
    }

    /**
     * Reconcile the participant list with the group's current membership without
     * disturbing the pivots (unread_count, last_read_at) of people already in the chat.
     */
    private function syncParticipants(Group $group, Conversation $conversation): void
    {
        $memberIds = $group->members()->pluck('users.id')->all();
        $participantIds = $conversation->participants()->pluck('users.id')->all();

        $toAdd = array_diff($memberIds, $participantIds);
        $toRemove = array_diff($participantIds, $memberIds);

        if ($toAdd !== []) {
            $conversation->participants()->attach($toAdd);
        }

        if ($toRemove !== []) {
            $conversation->participants()->detach($toRemove);
        }
    }
}
