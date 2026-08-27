<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Whereabouts;

/**
 * Why someone is away on a given day. Both reasons are shown to colleagues only as the
 * generic "Fjarverandi" (away) — the specific reason is private to the person themselves,
 * so recording sick leave never discloses it to the whole staff. {@see Whereabouts}.
 */
enum AbsenceType: string
{
    case Orlof = 'orlof';
    case Veikindi = 'veikindi';

    public function label(): string
    {
        return __("enums.absence.{$this->value}");
    }
}
