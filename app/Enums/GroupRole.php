<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A member's role within a single group. Distinct from the app-wide Spatie roles — a
 * user can own one group and merely belong to another. `managerFlag === 'Y'` from the
 * staff sync makes a user the Owner of their own organisation group.
 */
enum GroupRole: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Member = 'member';

    public function label(): string
    {
        return __("enums.group_role.{$this->value}");
    }

    public function canModerate(): bool
    {
        return $this === self::Owner || $this === self::Moderator;
    }
}
