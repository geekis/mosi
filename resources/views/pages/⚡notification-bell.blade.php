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

<x-ui.dropdown position="bottom" align="end">
    <x-slot:trigger>
        <button type="button" class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-mist-600 hover:bg-sand-200 dark:text-zinc-300 dark:hover:bg-zinc-700" aria-label="{{ __('Notifications') }}">
            <x-ui.icon name="bell" variant="mini" class="size-5" />
            @if ($unreadCount > 0)
                <span class="absolute -end-0.5 -top-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-coral-500 px-1 text-[10px] font-semibold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
            @endif
        </button>
    </x-slot:trigger>

    <x-ui.menu class="w-80">
        <div class="flex items-center justify-between px-2 py-1">
            <x-ui.heading size="sm">{{ __('Notifications') }}</x-ui.heading>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-brand-600 hover:text-brand-700">{{ __('Mark all read') }}</button>
            @endif
        </div>

        <x-ui.menu.separator />

        @forelse ($items as $item)
            <a href="{{ $item->data['url'] ?? route('dashboard') }}" wire:navigate wire:click="markRead('{{ $item->id }}')" wire:key="notif-{{ $item->id }}"
               class="flex items-start gap-2 rounded-xl px-2 py-2 hover:bg-sand-100 dark:hover:bg-white/5 {{ $item->read_at ? 'opacity-60' : '' }}">
                @unless ($item->read_at)
                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500"></span>
                @endunless
                <div class="min-w-0">
                    <x-ui.text size="sm" class="text-ink-700 dark:text-zinc-300">{{ __('notifications.'.($item->data['key'] ?? 'mention').'.body', $item->data['params'] ?? []) }}</x-ui.text>
                    <x-ui.text size="xs" class="text-mist-400">{{ $item->created_at?->diffForHumans() }}</x-ui.text>
                </div>
            </a>
        @empty
            <div class="px-2 py-6 text-center">
                <x-ui.text size="sm">{{ __('No notifications yet.') }}</x-ui.text>
            </div>
        @endforelse

        <x-ui.menu.separator />

        <x-ui.menu.item :href="route('notifications')" icon="inbox">{{ __('See all notifications') }}</x-ui.menu.item>
    </x-ui.menu>
</x-ui.dropdown>
