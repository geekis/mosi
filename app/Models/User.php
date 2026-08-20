<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AssignmentStatus;
use App\Enums\AuthMethod;
use App\Enums\PersonType;
use Database\Factories\UserFactory;
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
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $external_id
 * @property string $display_name
 * @property string|null $given_name
 * @property string|null $kennitala Decrypted on read; never serialised.
 * @property string|null $kennitala_hash
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
 * @property string|null $locale
 * @property bool $is_active
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
    'auth_method', 'locale', 'is_active', 'password_changed_at', 'password',
])]
#[Hidden(['kennitala', 'kennitala_hash', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
        ];
    }

    /**
     * Kennitala is encrypted at rest and paired with a SHA-256 hash for lookups. Both
     * derive from the digits only, so a value with or without the hyphen matches. This
     * is a mutator rather than the `encrypted` cast so the hash stays in lockstep.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function kennitala(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : Crypt::decryptString($value),
            set: function (?string $value): array {
                if ($value === null || $value === '') {
                    return ['kennitala' => null, 'kennitala_hash' => null];
                }

                $digits = self::normaliseKennitala($value);

                return [
                    'kennitala' => Crypt::encryptString($digits),
                    'kennitala_hash' => self::hashKennitala($digits),
                ];
            },
        );
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
}
