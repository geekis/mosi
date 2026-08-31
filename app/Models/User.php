<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AssignmentStatus;
use App\Enums\AuthMethod;
use App\Enums\PersonType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $external_id
 * @property string $display_name
 * @property string|null $given_name
 * @property string|null $kennitala Decrypted on read; never serialised.
 * @property string|null $kennitala_hash
 * @property int|null $birth_month Derived from the kennitala; month/day only, never the year.
 * @property int|null $birth_day
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $employee_number
 * @property string|null $person_type
 * @property string|null $job_title
 * @property string|null $job_id
 * @property string|null $organization_id
 * @property string|null $organization_name
 * @property string|null $parent_organization_id
 * @property string|null $assignment_status
 * @property Carbon|null $assignment_starts_at
 * @property Carbon|null $assignment_ends_at
 * @property int|null $assignment_percent
 * @property bool $is_manager
 * @property string|null $supervisor_external_id
 * @property int|null $supervisor_id
 * @property string|null $location_name
 * @property string|null $location_address
 * @property AuthMethod $auth_method
 * @property string|null $entra_oid Immutable Entra object ID; bound on first SSO login.
 * @property string|null $locale
 * @property string|null $bio
 * @property array<string, bool>|null $notification_preferences
 * @property bool $is_active
 * @property Carbon|null $last_active_at
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'external_id', 'display_name', 'email', 'phone', 'employee_number', 'person_type',
    'job_title', 'job_id', 'organization_id', 'organization_name', 'parent_organization_id',
    'assignment_status', 'assignment_starts_at', 'assignment_ends_at', 'assignment_percent',
    'is_manager', 'supervisor_external_id', 'supervisor_id', 'location_name', 'location_address',
    'auth_method', 'locale', 'bio', 'notification_preferences', 'is_active', 'password_changed_at', 'password',
])]
#[Hidden(['kennitala', 'kennitala_hash', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HasMedia, HasName, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithMedia, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'auth_method' => AuthMethod::class,
            'is_manager' => 'boolean',
            'is_active' => 'boolean',
            'assignment_starts_at' => 'date',
            'assignment_ends_at' => 'date',
            'assignment_percent' => 'integer',
            'birth_month' => 'integer',
            'birth_day' => 'integer',
            'notification_preferences' => 'array',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * A user is considered online if they made a request within this many minutes.
     */
    public const int ONLINE_WITHIN_MINUTES = 5;

    /**
     * Whether the user has been active recently enough to show as online.
     */
    public function isOnline(): bool
    {
        return $this->last_active_at !== null
            && $this->last_active_at->greaterThan(now()->subMinutes(self::ONLINE_WITHIN_MINUTES));
    }

    /**
     * Whether the user wants a given notification type. Opt-out model: unknown or unset
     * keys default to enabled. Gates every channel (database, mail, push).
     */
    public function wantsNotification(string $key): bool
    {
        return ($this->notification_preferences[$key] ?? true) !== false;
    }

    /**
     * Who reaches the Filament admin panel. employee never; group-owner's scoped access
     * is handled per-resource by policies (.ai/rules/admin.md).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRoleName('super-admin', 'comms', 'group-owner');
    }

    public function getFilamentName(): string
    {
        return $this->display_name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatarUrl();
    }

    /**
     * Kennitala is encrypted at rest and paired with a SHA-256 hash for lookups. Both
     * derive from the digits only, so a value with or without the hyphen matches. This
     * is a mutator rather than the `encrypted` cast so the hash — and the derived
     * birth_month/birth_day — stay in lockstep with it.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function kennitala(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : Crypt::decryptString($value),
            set: function (?string $value): array {
                if ($value === null || $value === '') {
                    return ['kennitala' => null, 'kennitala_hash' => null, 'birth_month' => null, 'birth_day' => null];
                }

                $digits = self::normaliseKennitala($value);

                return [
                    'kennitala' => Crypt::encryptString($digits),
                    'kennitala_hash' => self::hashKennitala($digits),
                    ...self::birthdayFromKennitala($digits) ?? ['birth_month' => null, 'birth_day' => null],
                ];
            },
        );
    }

    /**
     * Extract the birth month and day (never the year) from a kennitala. The first four
     * digits are DDMM; a company kennitala adds 40 to the day, so those — and any value
     * that does not resolve to a real month/day — return null.
     *
     * @return array{birth_month: int, birth_day: int}|null
     */
    public static function birthdayFromKennitala(?string $kennitala): ?array
    {
        $digits = self::normaliseKennitala((string) $kennitala);

        if (strlen($digits) < 4) {
            return null;
        }

        $day = (int) substr($digits, 0, 2);
        $month = (int) substr($digits, 2, 2);

        if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
            return null;
        }

        return ['birth_month' => $month, 'birth_day' => $day];
    }

    /**
     * Whether today is this person's birthday.
     */
    public function birthdayIsToday(): bool
    {
        return $this->birth_month === now()->month && $this->birth_day === now()->day;
    }

    /**
     * The next occurrence of this person's birthday (today if it falls today), or null
     * when no birthday is known. Year-agnostic: it is always this year or next.
     */
    public function nextBirthday(): ?CarbonImmutable
    {
        if ($this->birth_month === null || $this->birth_day === null) {
            return null;
        }

        $today = CarbonImmutable::today();
        $next = CarbonImmutable::createFromDate($today->year, $this->birth_month, $this->birth_day)->startOfDay();

        return $next->lt($today) ? $next->addYear() : $next;
    }

    /**
     * The whole name is stored as written (patronymics are not surnames — never split
     * or sort by the last token). `given_name`, the first token, is derived here for
     * greetings and for sorting people.
     *
     * @return Attribute<string, string>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): array => [
                'display_name' => $value,
                'given_name' => (string) Str::of($value)->squish()->before(' '),
            ],
        );
    }

    public function personType(): ?PersonType
    {
        return $this->person_type === null ? null : PersonType::tryFrom($this->person_type);
    }

    public function assignmentStatus(): ?AssignmentStatus
    {
        return $this->assignment_status === null ? null : AssignmentStatus::tryFrom($this->assignment_status);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    /**
     * People this user follows.
     *
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    /**
     * People who follow this user.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->whereKey($user->id)->exists();
    }

    /**
     * Groups this user belongs to.
     *
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('role')->withTimestamps();
    }

    /**
     * This user's whereabouts registrations (one per day at most).
     *
     * @return HasMany<Whereabouts, $this>
     */
    public function whereabouts(): HasMany
    {
        return $this->hasMany(Whereabouts::class);
    }

    /**
     * The whereabouts registration for a given day, if any. Reads from an already-loaded
     * `whereabouts` relation when present (so a directory list can eager-load the day
     * without an N+1), otherwise queries for it.
     */
    public function whereaboutsOn(CarbonInterface $date): ?Whereabouts
    {
        if ($this->relationLoaded('whereabouts')) {
            return $this->whereabouts->first(fn (Whereabouts $w): bool => $w->date->isSameDay($date));
        }

        return $this->whereabouts()->whereDate('date', $date)->with('workLocation')->first();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Whether the user has any of the given app-wide roles, checked via a query so the
     * strict lazy-loading guard is not tripped when `roles` is not eager-loaded.
     */
    public function hasAnyRoleName(string ...$roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public static function findByKennitala(string $kennitala): ?self
    {
        return static::query()
            ->where('kennitala_hash', self::hashKennitala(self::normaliseKennitala($kennitala)))
            ->first();
    }

    public static function hashKennitala(string $kennitala): string
    {
        return hash('sha256', self::normaliseKennitala($kennitala));
    }

    public static function normaliseKennitala(string $kennitala): string
    {
        return (string) preg_replace('/\D/', '', $kennitala);
    }

    public function initials(): string
    {
        $initials = Str::initials($this->display_name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Profile imagery is user-uploaded, one file each: a square avatar and a wide
     * banner. Both are self-service (never from the staff sync).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('banner')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('avatar')
            ->nonQueued()
            ->fit(Fit::Crop, 128, 128);
    }

    /**
     * The avatar URL (thumb conversion) or null when the user has not uploaded one —
     * the view falls back to initials.
     */
    public function avatarUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('avatar', 'thumb');

        return $url === '' ? null : $url;
    }

    public function bannerUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('banner');

        return $url === '' ? null : $url;
    }
}
