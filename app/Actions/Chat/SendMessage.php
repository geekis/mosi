<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Actions\Mentions\NotifyMentions;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Persist a chat message, then broadcast it. Persistence is the source of truth — a
 * dropped socket must never lose a message (.ai/rules/realtime.md). Unread counters are
 * denormalised on the participant pivot: bumped for everyone else, cleared for the
 * sender (who has by definition just read the thread).
 */
final class SendMessage
{
    public function __construct(
        private readonly NotifyMentions $notifyMentions,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function __invoke(User $sender, Conversation $conversation, string $body, array $attachments = []): Message
    {
        $body = trim($body);

        if ($body === '' && $attachments === []) {
            throw new DomainException('A message needs text or an attachment.');
        }

        if (! $conversation->hasParticipant($sender)) {
            throw new DomainException('You are not a participant in this conversation.');
        }

        $message = $conversation->messages()->create([
            'user_id' => $sender->id,
            'body' => $body,
        ]);

        foreach ($attachments as $file) {
            // The mime only decides which display bucket the file lands in, not a security
            // boundary. The videos/attachments collections are unrestricted so a stored
            // file whose sniffed mime differs from this never throws and loses the message.
            $mime = (string) $file->getMimeType();

            $collection = match (true) {
                str_starts_with($mime, 'image/') => 'images',
                str_starts_with($mime, 'video/') => 'videos',
                default => 'attachments',
            };

            $message->addMedia($file->getRealPath())
                ->usingName($file->getClientOriginalName())
                ->usingFileName($file->hashName())
                ->toMediaCollection($collection);
        }

        $conversation->update(['last_message_at' => $message->created_at]);

        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $sender->id)
            ->increment('unread_count');

        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $sender->id)
            ->update(['last_read_at' => now(), 'unread_count' => 0]);

        // A chat @mention may only reach people in the thread, so the participants are the
        // whole audience — a handle typed for anyone else notifies no one.
        ($this->notifyMentions)($message, $sender, $body, $conversation->participants);

        broadcast(new MessageSent($message))->toOthers();

        return $message;
    }
}
