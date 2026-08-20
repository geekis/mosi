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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasSlug, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name', 'description', 'type', 'visibility', 'external_organization_id', 'created_by',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

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
            ->saveSlugsTo('slug');
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
