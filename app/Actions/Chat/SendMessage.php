<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Persist a chat message, then broadcast it. Persistence is the source of truth — a
 * dropped socket must never lose a message (.ai/rules/realtime.md). Unread counters are
 * denormalised on the participant pivot: bumped for everyone else, cleared for the
 * sender (who has by definition just read the thread).
 */
final class SendMessage
{
    public function __invoke(User $sender, Conversation $conversation, string $body): Message
    {
        $body = trim($body);

        if ($body === '') {
            throw new DomainException('A message cannot be empty.');
        }

        if (! $conversation->hasParticipant($sender)) {
            throw new DomainException('You are not a participant in this conversation.');
        }

        $message = $conversation->messages()->create([
            'user_id' => $sender->id,
            'body' => $body,
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $sender->id)
            ->increment('unread_count');

        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $sender->id)
            ->update(['last_read_at' => now(), 'unread_count' => 0]);

        broadcast(new MessageSent($message))->toOthers();

        return $message;
    }
}
