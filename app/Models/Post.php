<?php

namespace App\Models;

use App\Concerns\HasReactions;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $group_id
 * @property string $body
 * @property bool $is_official
 * @property bool $is_pinned
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasReactions, InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'group_id', 'body', 'is_official', 'is_pinned', 'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

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
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Posts the user is allowed to see: their own (drafts included), plus published posts
     * that are personal (no group) or whose group is visible to them. A secret group's
     * posts never leak to a non-member — enforced here, in the query (.ai/rules/auth.md).
     *
     * @param  Builder<Post>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $outer) use ($user): void {
            $outer->where('user_id', $user->id)
                ->orWhere(function (Builder $published) use ($user): void {
                    $published->published()->where(function (Builder $visible) use ($user): void {
                        $visible->whereNull('group_id')
                            ->orWhereIn('group_id', Group::query()->visibleTo($user)->select('id'));
                    });
                });
        });
    }

    /**
     * The personalised feed (fan-out-on-read): published posts from the user's groups,
     * from people they follow, and their own — always intersected with visibleTo() so a
     * secret group never leaks even if the author is followed. Pinned and official posts
     * are boosted; the ordering ends on id so cursor pagination is stable.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeFeedFor(Builder $query, User $user): void
    {
        $memberGroupIds = $user->groups()->pluck('groups.id')->all();
        $followedIds = $user->following()->pluck('users.id')->all();

        $query->visibleTo($user)
            ->published()
            ->where(function (Builder $relevant) use ($user, $memberGroupIds, $followedIds): void {
                $relevant->whereIn('group_id', $memberGroupIds)
                    ->orWhereIn('user_id', $followedIds)
                    ->orWhere('user_id', $user->id);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_official')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /**
     * The "Latest" tab: every published post the user is allowed to see, newest first —
     * unfiltered by group membership or follows, but still intersected with visibleTo()
     * so a secret group never leaks. Ordering ends on id for stable cursor pagination.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeLatestFor(Builder $query, User $user): void
    {
        $query->visibleTo($user)
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }
}
