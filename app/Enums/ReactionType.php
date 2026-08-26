<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The fixed palette of post/comment reactions — Facebook-style, never a single like
 * (spec §7). The order here is the order they appear in the hover picker.
 */
enum ReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Care = 'care';
    case Haha = 'haha';
    case Wow = 'wow';
    case Sad = 'sad';

    public function emoji(): string
    {
        return match ($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Care => '🤗',
            self::Haha => '😆',
            self::Wow => '😮',
            self::Sad => '😢',
        };
    }

    public function label(): string
    {
        return __("enums.reaction.{$this->value}");
    }
}
