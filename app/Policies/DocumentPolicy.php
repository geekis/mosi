<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Documents are internal to all staff, but only content managers (comms) and admins may
 * add or change them.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $document->is_published || $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Document $document): bool
    {
        return $document->uploaded_by === $user->id || $this->manage($user);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    private function manage(User $user): bool
    {
        return $user->hasAnyRoleName('comms', 'super-admin');
    }
}
