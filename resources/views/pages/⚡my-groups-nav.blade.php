<?php

use App\Notifications\NewGroupPost;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The sidebar "My groups" section with a live unread-post badge per group. It subscribes
 * to each of the user's group channels over Reverb (getListeners) and re-renders when a
 * post is published, and keeps a slow poll as a fallback for missed pings after a
 * reconnect (.ai/rules/realtime.md). Membership-scoped, so a secret group never shows to
 * a non-member.
 */
new class extends Component {
    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return Auth::user()->groups()->pluck('groups.id')
            ->mapWithKeys(fn (int $id): array => ["echo-private:group.{$id},GroupPostPublished" => '$refresh'])
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Group>
     */
    #[Computed]
    public function groups()
    {
        return Auth::user()->groups()->with('media')->get()->sortBy(fn ($group) => $group->name);
    }

    /**
     * Unread new-post counts keyed by group id, from the group_post notifications.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    #[Computed]
    public function unread()
    {
        return Auth::user()->unreadNotifications()
            ->where('type', NewGroupPost::class)
            ->get()
            ->groupBy(fn ($notification) => (int) ($notification->data['group_id'] ?? 0))
            ->map->count();
    }
}; ?>

<div wire:poll.60s>
    @if ($this->groups->isNotEmpty())
        <flux:sidebar.nav>
            {{-- Mirrors Flux's own sidebar-group label; hidden when the rail collapses. --}}
            <div class="px-3 py-2 text-sm font-medium leading-none text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden">
                {{ __('My groups') }}
            </div>

            @foreach ($this->groups as $group)
                <flux:sidebar.item
                    :href="route('groups.show', $group)"
                    :current="request()->routeIs('groups.show') && request()->route('group')?->is($group)"
                    :badge="($this->unread[$group->id] ?? 0) ?: null"
                    badge-color="red"
                    wire:navigate
                    wire:key="sidebar-group-{{ $group->id }}"
                >
                    <x-slot:icon>
                        @if ($group->avatarUrl())
                            <flux:avatar :src="$group->avatarUrl()" size="xs" />
                        @else
                            <flux:icon icon="user-group" variant="outline" class="size-4" />
                        @endif
                    </x-slot:icon>

                    {{ $group->name }}
                </flux:sidebar.item>
            @endforeach
        </flux:sidebar.nav>
    @endif
</div>
