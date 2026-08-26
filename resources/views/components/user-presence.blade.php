@props(['user'])

@php($online = $user->isOnline())

<span {{ $attributes->class('inline-flex items-center gap-1.5 text-xs') }}>
    <span class="size-2 shrink-0 rounded-full {{ $online ? 'bg-green-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"></span>
    <span class="text-zinc-500 dark:text-zinc-400">
        @if ($online)
            {{ __('Active now') }}
        @elseif ($user->last_active_at)
            {{ __('Last active :time', ['time' => $user->last_active_at->diffForHumans()]) }}
        @else
            {{ __('Offline') }}
        @endif
    </span>
</span>
