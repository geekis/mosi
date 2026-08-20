<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorisation for viewing and editing staff profiles. Profiles are an internal
 * directory: any authenticated colleague may view an active profile, but only the
 * owner (or a super-admin) may edit one. A departed (inactive) member's profile is
 * hidden from everyone but themselves and admins.
 */
class UserPolicy
{
    public function view(User $viewer, User $profile): bool
    {
        if ($viewer->is($profile)) {
            return true;
        }

        if ($viewer->hasAnyRoleName('super-admin')) {
            return true;
        }

        return $profile->is_active;
    }

    public function update(User $viewer, User $profile): bool
    {
        return $viewer->is($profile) || $viewer->hasAnyRoleName('super-admin');
    }
}
