<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A person's response to an event invitation. Absence of a row means "not responded".
 */
enum RsvpStatus: string
{
    case Going = 'going';
    case Maybe = 'maybe';
    case Declined = 'declined';

    public function label(): string
    {
        return __("enums.rsvp_status.{$this->value}");
    }
}
