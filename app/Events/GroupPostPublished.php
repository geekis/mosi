<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a post is published in a group, so members' sidebars refresh their
 * unread badge live. The payload is just the group id — the badge count itself is read
 * from the notifications table, never from the broadcast. The channel is private and
 * authorised to members only in routes/channels.php (.ai/rules/realtime.md).
 */
class GroupPostPublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $groupId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.'.$this->groupId)];
    }

    public function broadcastAs(): string
    {
        return 'GroupPostPublished';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return ['group_id' => $this->groupId];
    }
}
