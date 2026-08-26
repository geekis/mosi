<?php

namespace App\Models;

use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $slug
 * @property GroupType $type
 * @property GroupVisibility $visibility
 * @property string|null $external_organization_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Group extends Model implements HasMedia
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasSlug, HasTranslations, InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name', 'description', 'type', 'visibility', 'external_organization_id', 'created_by',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /**
     * Office-document mime types accepted into the members-only `files` collection.
     *
     * @var list<string>
     */
    public const array FILE_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/csv',
        'text/plain',
    ];

    /**
     * Image and video mime types accepted into the members-only `gallery` collection.
     *
     * @var list<string>
     */
    public const array GALLERY_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'video/mp4',
        'video/quicktime',
        'video/webm',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GroupType::class,
            'visibility' => GroupVisibility::class,
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function (Group $group): string {
                $names = $group->getTranslations('name');

                return $names['is'] ?? $names['en'] ?? (reset($names) ?: 'group');
            })
            ->saveSlugsTo('slug')
            // The slug is a permalink — renaming a group must not break its URL.
            ->doNotGenerateSlugsOnUpdate();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        // The group's profile image (public, like the banner).
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        // Members-only attachments live on the private `local` disk: their URLs must never
        // be public. They are streamed through GroupMediaController after a membership
        // check (.ai/rules/auth.md) — never via getUrl(). Mime types are enforced in the
        // upload Actions (UploadGroupFile / UploadGroupMedia) rather than via
        // acceptsMimeTypes(), whose content-based finfo check disagrees with the browser
        // mime on some real uploads and rejects test fixtures.
        $this->addMediaCollection('files')
            ->useDisk('local');

        $this->addMediaCollection('gallery')
            ->useDisk('local');
    }

    public function bannerUrl(): ?string
    {
        $banner = $this->getFirstMedia('banner');

        return $banner?->getUrl();
    }

    public function avatarUrl(): ?string
    {
        $avatar = $this->getFirstMedia('avatar');

        return $avatar?->getUrl();
    }

    /**
     * The media in a members-only collection, loading the relation if needed so the
     * strict lazy-loading guard is not tripped when a child component renders.
     *
     * @return MediaCollection<int, Media>
     */
    public function mediaIn(string $collection): MediaCollection
    {
        return $this->loadMissing('media')->getMedia($collection);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<GroupJoinRequest, $this>
     */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class);
    }

    /**
     * The group's dedicated chat, if one has been opened yet.
     *
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Groups the user is allowed to see: everything except secret groups they are not a
     * member of. This is the single place visibility is decided — callers scope, they do
     * not filter in PHP or rely on the UI (.ai/rules/auth.md).
     *
     * @param  Builder<Group>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user): void {
            $q->where('visibility', '!=', GroupVisibility::Secret->value)
                ->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->id));
        });
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }
}
