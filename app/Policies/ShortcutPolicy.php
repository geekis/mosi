<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shortcut;
use App\Models\User;

/**
 * Mega-menu shortcuts are visible to every authenticated user; only content managers
 * and admins may manage them. The manage abilities also gate the Filament resource.
 */
class ShortcutPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, Shortcut $shortcut): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Shortcut $shortcut): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Shortcut $shortcut): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasAnyRoleName('comms', 'super-admin');
    }
}
