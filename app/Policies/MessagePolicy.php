<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Only participants of a message's conversation may see it — the same predicate that
 * gates the conversation itself ({@see ConversationPolicy}). This is what stops a chat
 * mention from ever notifying someone outside the thread.
 */
class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->hasParticipant($user);
    }
}
