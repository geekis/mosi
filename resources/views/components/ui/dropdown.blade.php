@props([
    'align' => 'start',
    'position' => 'bottom',
])

{{--
    Click-to-open dropdown. Pass the opener in a `trigger` slot and the panel
    (<x-ui.menu> or custom) as the default slot. Closes on outside-click, Esc, and after
    any click inside the panel (menu selection).
--}}
@php
    $placement = [
        'bottom-start' => 'top-full start-0 mt-2 origin-top',
        'bottom-end' => 'top-full end-0 mt-2 origin-top',
        'top-start' => 'bottom-full start-0 mb-2 origin-bottom',
        'top-end' => 'bottom-full end-0 mb-2 origin-bottom',
    ][$position.'-'.$align] ?? 'top-full start-0 mt-2 origin-top';
@endphp

<div x-data="{ open: false }" x-on:keydown.escape.stop="open = false" class="relative inline-flex">
    <div class="contents" x-on:click="open = ! open">{{ $trigger }}</div>

    <div
        x-show="open"
        x-cloak
        x-transition.scale.95
        x-on:click.outside="open = false"
        x-on:click="open = false"
        class="absolute z-50 {{ $placement }}"
    >
        {{ $slot }}
    </div>
</div>
