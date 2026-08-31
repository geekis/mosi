@props(['content' => null])

{{-- Lightweight hover/focus tooltip (Alpine). Wraps its trigger slot. --}}
<span
    x-data="{ show: false }"
    class="relative inline-flex"
    @mouseenter="show = true" @mouseleave="show = false"
    @focusin="show = true" @focusout="show = false"
>
    {{ $slot }}

    @if ($content)
        <span
            x-show="show" x-cloak x-transition.opacity.duration.100ms
            role="tooltip"
            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded-lg bg-ink-900 px-2 py-1 text-xs font-medium text-white shadow-soft"
        >{{ $content }}</span>
    @endif
</span>
