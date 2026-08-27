<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkLocation;

/**
 * Work locations are a picklist visible to every authenticated user; only content
 * managers and admins may manage them. The manage abilities also gate the Filament
 * resource (.ai/rules/admin.md).
 */
class WorkLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, WorkLocation $workLocation): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, WorkLocation $workLocation): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, WorkLocation $workLocation): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasAnyRoleName('comms', 'super-admin');
    }
}
