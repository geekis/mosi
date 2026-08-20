<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The fixed set of reactions — a small palette of emoji, never a single like (spec §7).
 */
enum ReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Celebrate = 'celebrate';
    case Insightful = 'insightful';
    case Funny = 'funny';
    case Support = 'support';

    public function emoji(): string
    {
        return match ($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Celebrate => '🎉',
            self::Insightful => '💡',
            self::Funny => '😄',
            self::Support => '🙌',
        };
    }

    public function label(): string
    {
        return __("enums.reaction.{$this->value}");
    }
}
