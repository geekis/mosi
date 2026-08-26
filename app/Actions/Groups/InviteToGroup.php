<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\User;
use App\Notifications\GroupInvitationReceived;
use DomainException;

/**
 * Invite a colleague into a group. This is the only path into a secret group, and it
 * bypasses the join-request queue on a closed one — which is why the caller must hold
 * the `invite` policy ability (owners/moderators), not mere membership. The invitee
 * consents by accepting; nobody is added to a group silently.
 */
final class InviteToGroup
{
    public function __invoke(User $inviter, Group $group, User $invitee): GroupInvitation
    {
        if ($inviter->is($invitee)) {
            throw new DomainException('You cannot invite yourself.');
        }

        if ($group->hasMember($invitee)) {
            throw new DomainException('The user is already a member.');
        }

        $invitation = GroupInvitation::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $invitee->id],
            ['invited_by' => $inviter->id],
        );

        // Notify only on the first invitation, never again on a repeat click. Eager-load
        // what the notification renders so it does not trip the lazy-loading guard.
        if ($invitation->wasRecentlyCreated) {
            $invitation->load(['group', 'inviter']);
            $invitee->notify(new GroupInvitationReceived($invitation));
        }

        return $invitation;
    }
}
