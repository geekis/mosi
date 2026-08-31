@props([
    'href' => null,
    'icon' => null,
    'current' => false,
    'navigate' => true,
    'badge' => null,
])

{{-- `icon` may be a string (Heroicon name) or a slot (e.g. an avatar). An optional
     trailing `badge` shows a count. Items with an icon also carry a hover tooltip that
     only appears when the sidebar rail is collapsed (see railTooltip in app.js). --}}
@php
    $iconIsSlot = $icon instanceof \Illuminate\View\ComponentSlot;
    $hasIcon = $iconIsSlot || $icon;
    $tip = trim(preg_replace('/\s+/', ' ', strip_tags((string) $slot)));
    $classes = [
        'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition-colors group-data-[collapsed=true]/shell:lg:justify-center',
        'bg-sand-100 text-ink-900 dark:bg-white/10 dark:text-white' => $current,
        'text-taupe-600 hover:bg-sand-100 hover:text-ink-900 dark:text-zinc-400 dark:hover:text-white' => ! $current,
    ];
@endphp

<a
    href="{{ $href }}"
    @if ($navigate) wire:navigate @endif
    @if ($current) aria-current="page" @endif
    @if ($hasIcon)
        x-data="railTooltip()"
        data-tooltip="{{ $tip }}"
        x-on:mouseenter="enter()"
        x-on:mouseleave="leave()"
    @endif
    {{ $attributes->class($classes) }}
>
    @if ($iconIsSlot)
        <span class="shrink-0">{{ $icon }}</span>
    @elseif ($icon)
        <x-ui.icon :name="$icon" variant="mini" class="size-5 shrink-0" />
    @endif

    <span class="flex-1 truncate group-data-[collapsed=true]/shell:lg:hidden">{{ $slot }}</span>

    @if ($badge !== null && $badge !== '')
        <span class="ms-auto inline-flex min-w-5 items-center justify-center rounded-full bg-coral-500 px-1.5 text-xs font-semibold text-white group-data-[collapsed=true]/shell:lg:hidden">{{ $badge }}</span>
    @endif

    @if ($hasIcon)
        <template x-teleport="body">
            <div
                x-show="open"
                x-cloak
                x-transition.opacity.duration.100ms
                class="pointer-events-none fixed z-[70] -translate-y-1/2 whitespace-nowrap rounded-lg bg-ink-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-soft-lg"
                :style="`left:${x}px; top:${y}px`"
            >
                <span class="absolute -start-1 top-1/2 size-2 -translate-y-1/2 rotate-45 rounded-[2px] bg-ink-900"></span>
                <span x-text="label"></span>
            </div>
        </template>
    @endif
</a>
