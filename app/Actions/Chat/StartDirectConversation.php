<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use DomainException;

/**
 * Get the existing direct conversation between two people, or create it. Idempotent —
 * two people always share exactly one DM thread.
 */
final class StartDirectConversation
{
    public function __invoke(User $initiator, User $other): Conversation
    {
        if ($initiator->is($other)) {
            throw new DomainException('You cannot start a conversation with yourself.');
        }

        $existing = Conversation::query()
            ->where('type', ConversationType::Direct->value)
            ->whereHas('participants', fn ($q) => $q->whereKey($initiator->id))
            ->whereHas('participants', fn ($q) => $q->whereKey($other->id))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $conversation = Conversation::create([
            'type' => ConversationType::Direct,
            'created_by' => $initiator->id,
        ]);

        $conversation->participants()->attach([$initiator->id, $other->id]);

        return $conversation;
    }
}
