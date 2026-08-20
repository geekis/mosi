<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function markRead(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->update(['read_at' => now()]);
    }

    /**
     * @return array{unreadCount: int, items: \Illuminate\Notifications\DatabaseNotificationCollection}
     */
    public function with(): array
    {
        $user = Auth::user();

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(10)->get(),
        ];
    }
}; ?>

<flux:dropdown position="bottom" align="end">
    <button type="button" class="relative inline-flex h-9 w-9 items-center justify-center rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700" aria-label="{{ __('Notifications') }}">
        <flux:icon icon="bell" class="size-5 text-zinc-600 dark:text-zinc-300" />
        @if ($unreadCount > 0)
            <span class="absolute -end-0.5 -top-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <flux:menu class="w-80">
        <div class="flex items-center justify-between px-2 py-1">
            <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>
            @if ($unreadCount > 0)
                <flux:link class="cursor-pointer text-xs" wire:click="markAllRead">{{ __('Mark all read') }}</flux:link>
            @endif
        </div>

        <flux:menu.separator />

        @forelse ($items as $item)
            <a href="{{ $item->data['url'] ?? route('dashboard') }}" wire:navigate wire:click="markRead('{{ $item->id }}')" wire:key="notif-{{ $item->id }}"
               class="flex items-start gap-2 px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ $item->read_at ? 'opacity-60' : '' }}">
                @unless ($item->read_at)
                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary-500"></span>
                @endunless
                <div class="min-w-0">
                    <flux:text size="sm">{{ __('notifications.'.($item->data['key'] ?? 'mention').'.body', $item->data['params'] ?? []) }}</flux:text>
                    <flux:text size="xs" class="text-zinc-400">{{ $item->created_at?->diffForHumans() }}</flux:text>
                </div>
            </a>
        @empty
            <div class="px-2 py-6 text-center">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No notifications yet.') }}</flux:text>
            </div>
        @endforelse

        <flux:menu.separator />

        <flux:menu.item :href="route('notifications')" icon="inbox" wire:navigate>{{ __('See all notifications') }}</flux:menu.item>
    </flux:menu>
</flux:dropdown>
