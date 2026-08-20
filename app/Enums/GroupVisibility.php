<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Group visibility. A `secret` group must never leak to a non-member — in the feed,
 * search, the directory, mention autocomplete, notifications or the API. Enforce this in
 * the query, never in the UI (.ai/rules/auth.md).
 */
enum GroupVisibility: string
{
    case Open = 'open';     // anyone may see and join
    case Closed = 'closed'; // visible to all, join by request
    case Secret = 'secret'; // invisible to non-members

    public function label(): string
    {
        return __("enums.group_visibility.{$this->value}");
    }

    public function isSecret(): bool
    {
        return $this === self::Secret;
    }
}
