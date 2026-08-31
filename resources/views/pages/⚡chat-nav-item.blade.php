<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * The sidebar "Chat" link with a live unread badge. The count is the sum of the
 * denormalised unread_count across the user's conversations, so it covers both direct
 * messages and messages in any group they belong to. It updates live over Reverb — it
 * subscribes to each of the user's conversation channels — refreshes when a thread is
 * read ("chat-read"), and keeps a slow poll as a reconnect fallback.
 */
new class extends Component {
    public int $unread = 0;

    public function mount(): void
    {
        $this->refreshUnread();
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $echo = Conversation::query()->forUser(Auth::user())->pluck('id')
            ->mapWithKeys(fn (int $id): array => ["echo-private:conversation.{$id},MessageSent" => 'refreshUnread'])
            ->all();

        return array_merge(['chat-read' => 'refreshUnread'], $echo);
    }

    public function refreshUnread(): void
    {
        $this->unread = (int) DB::table('conversation_user')
            ->where('user_id', Auth::id())
            ->sum('unread_count');
    }
}; ?>

<x-ui.navlist.item
    icon="chat-bubble-left-right"
    :href="route('chat')"
    :current="request()->routeIs('chat')"
    :badge="$unread > 0 ? ($unread > 99 ? '99+' : (string) $unread) : null"
    wire:poll.30s="refreshUnread"
>
    {{ __('Chat') }}
</x-ui.navlist.item>
