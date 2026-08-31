@props([
    'href' => null,
    'icon' => null,
    'navigate' => true,
])

@php
    $classes = 'flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-sm text-ink-700 transition-colors hover:bg-sand-100 dark:text-zinc-200 dark:hover:bg-white/5';
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes->class($classes) }} role="menuitem">
        @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="size-4 shrink-0 text-mist-500" />@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }} role="menuitem">
        @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="size-4 shrink-0 text-mist-500" />@endif
        {{ $slot }}
    </button>
@endif
