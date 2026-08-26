<?php

namespace App\Notifications;

use App\Models\GroupInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a user they were invited into a group. Database-only, and not gated by a
 * notification preference — a pending invitation blocks nothing but should never be
 * missed. Naming the group to the invitee is intended disclosure, even for a secret
 * group: an owner chose to invite them. The link goes to the groups index, where the
 * invitation is accepted or declined — a secret group's own page would 404 until the
 * invitee accepts.
 */
class GroupInvitationReceived extends Notification
{
    use Queueable;

    public function __construct(public GroupInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => 'group_invitation',
            'params' => [
                'name' => $this->invitation->inviter->display_name,
                'group' => $this->invitation->group->name,
            ],
            'url' => route('groups'),
            'actor_id' => $this->invitation->invited_by,
        ];
    }
}
