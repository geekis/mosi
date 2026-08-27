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

/*
 * A group's live channel carries new-post pings for the sidebar badge. Only members may
 * subscribe, so it never reveals activity in a secret group (.ai/rules/realtime.md).
 */
Broadcast::channel('group.{groupId}', function (User $user, int $groupId): bool {
    return $user->groups()->whereKey($groupId)->exists();
});

/*
 * Presence is org-wide: everyone sees everyone's status dot, so a single tenant channel
 * carries the change pings. Any active user may listen; a deactivated account may not
 * (.ai/rules/realtime.md). The payload never names a user beyond their id + state.
 */
Broadcast::channel('presence', function (User $user): bool {
    return $user->is_active;
});
