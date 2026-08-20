<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Only participants may view or post to a conversation.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }
}
