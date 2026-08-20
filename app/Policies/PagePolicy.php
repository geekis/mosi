<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Static pages are readable by all staff when published; only content managers and
 * admins may manage them. The manage abilities also gate the Filament resource.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, Page $page): bool
    {
        return $page->is_published || $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasAnyRoleName('comms', 'super-admin');
    }
}
