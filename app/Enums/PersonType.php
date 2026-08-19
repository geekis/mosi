<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The staff-API `personType`.
 *
 * Only `Starfsmaður` (employee) is confirmed by HR. The full set is open, and per the
 * confirmed activation model person type does NOT gate login — everyone in the payload
 * gets an account (see .ai/rules/staff-api.md). The column is therefore stored as a
 * raw string; use {@see User::personType()} for a typed value that returns null on an
 * unknown value rather than throwing.
 */
enum PersonType: string
{
    case Employee = 'Starfsmaður';

    public function label(): string
    {
        return __("enums.person_type.{$this->value}");
    }
}
