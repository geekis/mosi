<?php

namespace Database\Factories;

use App\Enums\AbsenceType;
use App\Models\User;
use App\Models\Whereabouts;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Whereabouts>
 */
class WhereaboutsFactory extends Factory
{
    /**
     * Present at a work location by default. Use {@see self::absent()} for an away day.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'work_location_id' => WorkLocation::factory(),
            'absence' => null,
        ];
    }

    /**
     * An away day: an absence reason instead of a location.
     */
    public function absent(AbsenceType $reason = AbsenceType::Orlof): static
    {
        return $this->state(fn (): array => [
            'work_location_id' => null,
            'absence' => $reason,
        ]);
    }
}
