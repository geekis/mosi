<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Conversation channels are private. Channel names are guessable, so this callback is
 * the only thing stopping a user subscribing to someone else's thread — only
 * participants may listen (.ai/rules/realtime.md).
 */
Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation): bool {
    return $conversation->hasParticipant($user);
});
