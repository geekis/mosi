<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The sidebar "Chat" link with a live unread badge. The count is the sum of the
 * denormalised unread_count across the user's conversations, so it covers both direct
 * messages and messages in any group they belong to. It refreshes on a slow poll and
 * immediately when a conversation is read (the chat page dispatches "chat-read").
 */
new class extends Component {
    public int $unread = 0;

    public function mount(): void
    {
        $this->refreshUnread();
    }

    #[On('chat-read')]
    public function refreshUnread(): void
    {
        $this->unread = (int) DB::table('conversation_user')
            ->where('user_id', Auth::id())
            ->sum('unread_count');
    }
}; ?>

<flux:sidebar.item
    icon="chat-bubble-left-right"
    :href="route('chat')"
    :current="request()->routeIs('chat')"
    :badge="$unread > 0 ? ($unread > 99 ? '99+' : (string) $unread) : null"
    badge-color="red"
    wire:poll.30s="refreshUnread"
    wire:navigate
>
    {{ __('Chat') }}
</flux:sidebar.item>
