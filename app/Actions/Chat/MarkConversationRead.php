<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mark a conversation read for one participant: stamp last_read_at and zero their
 * denormalised unread counter.
 */
final class MarkConversationRead
{
    public function __invoke(User $user, Conversation $conversation): void
    {
        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now(), 'unread_count' => 0]);
    }
}
