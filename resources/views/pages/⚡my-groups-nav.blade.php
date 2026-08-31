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
        <x-ui.navlist class="mt-4">
            <div class="px-3 py-2 text-xs font-medium leading-none text-mist-400 group-data-[collapsed=true]/shell:lg:hidden">
                {{ __('My groups') }}
            </div>

            @foreach ($this->groups as $group)
                <x-ui.navlist.item
                    :href="route('groups.show', $group)"
                    :current="request()->routeIs('groups.show') && request()->route('group')?->is($group)"
                    :badge="($this->unread[$group->id] ?? 0) ?: null"
                    wire:key="sidebar-group-{{ $group->id }}"
                >
                    <x-slot:icon>
                        {{-- Sized to 20px so the group icon lines up with the other menu items' icons. --}}
                        <x-avatar :src="$group->avatarUrl()" :name="$group->name" icon="user-group" :presence="false" size="xs" class="[&>span]:!size-5" />
                    </x-slot:icon>

                    {{ $group->name }}
                </x-ui.navlist.item>
            @endforeach
        </x-ui.navlist>
    @endif
</div>
