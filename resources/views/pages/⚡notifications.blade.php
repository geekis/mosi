<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifications')] class extends Component {
    use WithPagination;

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * @return array{items: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    public function with(): array
    {
        return [
            'items' => Auth::user()->notifications()->latest()->paginate(20),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Notifications') }}</flux:heading>
        <flux:button size="sm" variant="ghost" wire:click="markAllRead">{{ __('Mark all read') }}</flux:button>
    </div>

    <div class="mt-6 space-y-2">
        @forelse ($items as $item)
            <a href="{{ $item->data['url'] ?? route('dashboard') }}" wire:navigate wire:key="notif-{{ $item->id }}"
               class="flex items-start gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800 {{ $item->read_at ? 'opacity-60' : '' }}">
                @unless ($item->read_at)
                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary-500"></span>
                @endunless
                <div class="min-w-0">
                    <flux:text>{{ __('notifications.'.($item->data['key'] ?? 'mention').'.body', $item->data['params'] ?? []) }}</flux:text>
                    <flux:text size="sm" class="text-zinc-400">{{ $item->created_at?->diffForHumans() }}</flux:text>
                </div>
            </a>
        @empty
            <flux:callout icon="inbox">{{ __('No notifications yet.') }}</flux:callout>
        @endforelse
    </div>

    <div class="mt-6">{{ $items->links() }}</div>
</div>
