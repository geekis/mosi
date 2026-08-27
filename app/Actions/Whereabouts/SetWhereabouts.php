<?php

declare(strict_types=1);

namespace App\Actions\Whereabouts;

use App\Enums\AbsenceType;
use App\Models\User;
use App\Models\Whereabouts;
use App\Models\WorkLocation;
use Carbon\CarbonInterface;
use DomainException;

/**
 * Register (or clear) where a person is on a single day. A day is either a work location
 * or an absence, never both; passing neither clears the day (deletes the row). The write
 * is an upsert keyed on (user, date), so re-registering a day just overwrites it.
 */
final class SetWhereabouts
{
    public function __invoke(User $user, CarbonInterface $date, ?WorkLocation $location = null, ?AbsenceType $absence = null): ?Whereabouts
    {
        if ($location !== null && $absence !== null) {
            throw new DomainException('A day is either a work location or an absence, not both.');
        }

        $day = $date->toDateString();

        if ($location === null && $absence === null) {
            Whereabouts::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $day)
                ->delete();

            return null;
        }

        // Match on the date part explicitly: the `date` cast persists a zeroed datetime,
        // so a plain where('date', $day) would miss the existing row and trip the unique
        // index. whereDate compares only the day, and works on MySQL and SQLite alike.
        $whereabouts = Whereabouts::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day)
            ->first()
            ?? new Whereabouts(['user_id' => $user->id, 'date' => $day]);

        $whereabouts->fill(['work_location_id' => $location?->id, 'absence' => $absence])->save();

        return $whereabouts;
    }
}
