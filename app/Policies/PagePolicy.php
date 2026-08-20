<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Static pages are readable by all staff when published; only content managers and
 * admins may edit them.
 */
class PagePolicy
{
    public function view(User $user, Page $page): bool
    {
        return $page->is_published || $user->hasAnyRoleName('comms', 'super-admin');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasAnyRoleName('comms', 'super-admin');
    }
}
