<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\GroupInvitation;

/**
 * Decline an invitation: simply clear it. The inviter may invite again later — the
 * unique (group, user) pair only guards against duplicate *pending* invitations.
 */
final class DeclineGroupInvitation
{
    public function __invoke(GroupInvitation $invitation): void
    {
        $invitation->delete();
    }
}
