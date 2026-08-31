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
        <x-ui.heading size="xl">{{ __('Notifications') }}</x-ui.heading>
        <x-ui.button size="sm" variant="ghost" wire:click="markAllRead">{{ __('Mark all read') }}</x-ui.button>
    </div>

    <div class="mt-6 space-y-2">
        @forelse ($items as $item)
            <a href="{{ $item->data['url'] ?? route('dashboard') }}" wire:navigate wire:key="notif-{{ $item->id }}">
                <x-ui.card class="flex items-start gap-3 {{ $item->read_at ? 'opacity-60' : '' }}">
                    @unless ($item->read_at)
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500"></span>
                    @endunless
                    <div class="min-w-0">
                        <x-ui.text class="text-ink-800 dark:text-zinc-200">{{ __('notifications.'.($item->data['key'] ?? 'mention').'.body', $item->data['params'] ?? []) }}</x-ui.text>
                        <x-ui.text size="sm" class="text-mist-400">{{ $item->created_at?->diffForHumans() }}</x-ui.text>
                    </div>
                </x-ui.card>
            </a>
        @empty
            <x-ui.alert icon="inbox">{{ __('No notifications yet.') }}</x-ui.alert>
        @endforelse
    </div>

    <div class="mt-6">{{ $items->links() }}</div>
</div>
