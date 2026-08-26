<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ConversationType $type
 * @property int|null $group_id
 * @property string|null $title
 * @property int|null $created_by
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Conversation extends Model
{
    /** @var list<string> */
    protected $fillable = ['type', 'group_id', 'title', 'created_by', 'last_message_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['last_read_at', 'unread_count'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The group this chat belongs to, for a dedicated group conversation.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    /**
     * The other person in a direct conversation, from a given viewer's perspective.
     */
    public function otherParticipant(User $user): ?User
    {
        return $this->participants->first(fn (User $participant): bool => $participant->id !== $user->id);
    }

    /**
     * The label to show a given viewer: a group's title, or the other person's name.
     */
    public function titleFor(User $user): string
    {
        if ($this->type === ConversationType::Group) {
            return $this->title ?? __('Group chat');
        }

        $other = $this->otherParticipant($user);

        if ($other === null) {
            return __('Conversation');
        }

        return $other->display_name;
    }

    /**
     * Conversations the user takes part in, most recent first.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->whereHas('participants', fn (Builder $q) => $q->whereKey($user->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }
}
