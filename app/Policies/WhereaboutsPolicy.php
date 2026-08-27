<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Whereabouts;

/**
 * The whereabouts board is open to every authenticated colleague, but a registration is
 * only ever editable by the person it belongs to — no one sets where someone else is.
 */
class WhereaboutsPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Whereabouts $whereabouts): bool
    {
        return true;
    }

    public function update(User $user, Whereabouts $whereabouts): bool
    {
        return $user->is($whereabouts->user);
    }

    public function delete(User $user, Whereabouts $whereabouts): bool
    {
        return $user->is($whereabouts->user);
    }
}
