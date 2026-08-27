<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A person's presentation-level presence, collapsed from Microsoft Graph's eleven
 * `availability` values into the four states the UI shows: a green, amber or red dot,
 * or an empty "offline" marker. The Graph enum is a Teams-client concern; the app only
 * ever cares which colour to paint.
 */
enum PresenceStatus: string
{
    case Available = 'available';
    case Away = 'away';
    case Busy = 'busy';
    case Offline = 'offline';

    /**
     * Collapse a Graph `availability` value onto one of our four states. Unknown or
     * missing values are treated as offline so a gap in the feed never shows as "busy".
     */
    public static function fromGraphAvailability(?string $availability): self
    {
        return match ($availability) {
            'Available', 'AvailableIdle' => self::Available,
            'Away', 'BeRightBack' => self::Away,
            'Busy', 'BusyIdle', 'DoNotDisturb', 'Focusing', 'InACall', 'InAMeeting', 'Presenting' => self::Busy,
            default => self::Offline,
        };
    }

    /**
     * The dot colour token. `offline` is intentionally rendered as an empty/white marker
     * by the frontend rather than a filled colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Away => 'amber',
            self::Busy => 'red',
            self::Offline => 'zinc',
        };
    }

    /**
     * Localised label for tooltips and the profile presence line.
     */
    public function label(): string
    {
        return __('presence.'.$this->value);
    }
}
