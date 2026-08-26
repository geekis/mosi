<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Message extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'user_id', 'body'];

    /**
     * Attachments mirror a post's: inline images in one collection, arbitrary files in
     * another, sharing the same validation (.ai/rules/realtime.md).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        // Videos and generic files are unrestricted: the collection is chosen from the
        // declared mime at send time (SendMessage), and a stored file whose sniffed mime
        // differs from what the browser declared must not throw and lose the message.
        $this->addMediaCollection('videos');

        $this->addMediaCollection('attachments');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
