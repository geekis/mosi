<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The staff-API `assignmentStatus` of a person's primary assignment.
 *
 * Only `Virkt starf` (active assignment) is confirmed by HR. The full set is open, and
 * per the confirmed activation model assignment status does NOT gate login — activation
 * is decided purely by payload presence (see .ai/rules/staff-api.md). The column is
 * stored as a raw string; use {@see User::assignmentStatus()} for a typed value that
 * returns null on an unknown value rather than throwing.
 */
enum AssignmentStatus: string
{
    case Active = 'Virkt starf';

    public function label(): string
    {
        return __("enums.assignment_status.{$this->value}");
    }
}
