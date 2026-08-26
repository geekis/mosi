<?php

namespace App\Models;

use App\Enums\RsvpStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $slug
 * @property string|null $location
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_all_day
 * @property int|null $group_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Event extends Model implements HasMedia
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasSlug, HasTranslations, InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at', 'is_all_day', 'group_id', 'created_by',
    ];

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function (Event $event): string {
                $titles = $event->getTranslations('title');

                return $titles['is'] ?? $titles['en'] ?? (reset($titles) ?: 'event');
            })
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function coverUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('cover');

        return $url === '' ? null : $url;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * People who have responded, with their status on the pivot.
     *
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('status')->withTimestamps();
    }

    public function rsvpFor(User $user): ?RsvpStatus
    {
        $pivot = $this->attendees()->find($user->id)?->getRelationValue('pivot');

        return $pivot === null ? null : RsvpStatus::tryFrom($pivot->status);
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at');
    }

    /**
     * Events the user may see: organisation-wide events (no group) plus events in groups
     * the user is a member of. A group event is member-only — visibility of the group
     * itself is not enough (.ai/rules/auth.md).
     *
     * @param  Builder<Event>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user): void {
            $q->whereNull('group_id')
                ->orWhereIn('group_id', Group::query()
                    ->whereHas('members', fn (Builder $m) => $m->whereKey($user->id))
                    ->select('id'));
        });
    }
}
