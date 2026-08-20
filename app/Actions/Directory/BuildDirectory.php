<?php

declare(strict_types=1);

namespace App\Actions\Directory;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The staff directory: active users only, paginated, ordered by given name (patronymics
 * are not surnames, so never sort by the last token — .ai/rules/localisation.md).
 * Filtering by department uses organization_name; location is deliberately not a filter
 * because its data quality is suspect (.ai/rules/staff-api.md).
 */
final class BuildDirectory
{
    /**
     * @param  array{search?: string|null, department?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function __invoke(array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $department = $filters['department'] ?? null;

        return User::query()
            ->active()
            ->with('media')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('display_name', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")
                        ->orWhere('organization_name', 'like', "%{$search}%");
                });
            })
            ->when($department, fn ($query, $department) => $query->where('organization_name', $department))
            ->orderBy('given_name')
            ->orderBy('display_name')
            ->paginate($perPage);
    }
}
