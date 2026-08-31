@props([
    'name' => null,
    'variant' => 'outline',
])

{{--
    Heroicon wrapper (blade-icons, already vendored). Variants map to the four Heroicon
    artboards; `name="loading"` renders an animated spinner. Any class/attribute passes
    through, so sizing with `class="size-4"` works as expected.
--}}
@php
    $prefix = ['outline' => 'o', 'solid' => 's', 'mini' => 'm', 'micro' => 'c'][$variant] ?? 'o';
    $defaultSize = ['outline' => 'size-6', 'solid' => 'size-6', 'mini' => 'size-5', 'micro' => 'size-4'][$variant] ?? 'size-6';
@endphp

@if ($name === 'loading')
    <svg {{ $attributes->class([$defaultSize, 'animate-spin shrink-0']) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
@else
    <x-dynamic-component :component="'heroicon-'.$prefix.'-'.$name" {{ $attributes->class($defaultSize) }} aria-hidden="true" />
@endif
