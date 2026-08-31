@props([
    'href' => null,
    'icon' => null,
    'current' => false,
    'navigate' => true,
])

@php
    $classes = [
        'inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-medium transition-colors',
        'bg-white text-ink-900 shadow-soft dark:bg-white/10 dark:text-white' => $current,
        'text-taupe-600 hover:bg-sand-200 hover:text-ink-900 dark:text-zinc-400 dark:hover:text-white' => ! $current,
    ];
@endphp

<a
    href="{{ $href }}"
    @if ($navigate) wire:navigate @endif
    @if ($current) aria-current="page" @endif
    {{ $attributes->class($classes) }}
>
    @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="size-4 shrink-0" />@endif
    {{ $slot }}
</a>
