<?php

declare(strict_types=1);

namespace App\Actions\Whereabouts;

use App\Models\Whereabouts;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The "Hvar er unnið" board for a single day: every active colleague who has registered a
 * whereabouts that day, with their person and location eager-loaded, ordered by given name
 * (patronymics are not surnames — .ai/rules/localisation.md). Fan-out-on-read is fine at
 * this staff size (.ai/rules/database.md); the sort is done in PHP to avoid a join purely
 * for ordering.
 *
 * The board can be narrowed by work location and by the person's division
 * (organization_name), and can exclude one person (the viewer, on the "other staff" board).
 */
final class BuildWhereaboutsBoard
{
    /**
     * @param  array{location_id?: int|null, division?: string|null, exclude_user_id?: int|null}  $filters
     * @return Collection<int, Whereabouts>
     */
    public function __invoke(CarbonInterface $date, array $filters = []): Collection
    {
        return Whereabouts::query()
            ->whereDate('date', $date->toDateString())
            ->whereHas('user', function (Builder $query) use ($filters): void {
                $query->where('is_active', true);

                if (! empty($filters['division'])) {
                    $query->where('organization_name', $filters['division']);
                }

                if (! empty($filters['exclude_user_id'])) {
                    $query->whereKeyNot($filters['exclude_user_id']);
                }
            })
            ->when(! empty($filters['location_id']), fn (Builder $query) => $query->where('work_location_id', $filters['location_id']))
            ->with(['user.media', 'workLocation'])
            ->get()
            ->sortBy(fn (Whereabouts $whereabouts): string => (string) $whereabouts->user->given_name)
            ->values();
    }
}
