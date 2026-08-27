<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user's Teams presence changes, so every open client repaints that
 * person's dot without a page load. Presence is org-wide information, so it rides a single
 * tenant channel authorised to any active user (routes/channels.php); the payload is just
 * the user id and the four-state string — never the availability detail or any model
 * (.ai/rules/realtime.md).
 */
class UserPresenceChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $userId, public string $status) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('presence')];
    }

    public function broadcastAs(): string
    {
        return 'UserPresenceChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'status' => $this->status,
        ];
    }
}
