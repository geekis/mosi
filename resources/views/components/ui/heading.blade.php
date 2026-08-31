@props([
    'size' => 'base',
    'level' => null,
])

{{-- Display-font heading. Named sizes map to a fixed scale (lg → text-base,
     xl → text-2xl) so headings stay visually consistent across the app. --}}
@php
    $sizes = [
        'xl' => 'text-2xl',
        'lg' => 'text-base',
        'base' => 'text-sm',
        'sm' => 'text-sm',
        'xs' => 'text-xs',
    ];
    $tag = $level ? 'h'.$level : 'div';
    $classes = 'font-display font-medium tracking-tight text-ink-900 dark:text-white '.($sizes[$size] ?? $sizes['base']);
@endphp

<{{ $tag }} {{ $attributes->class($classes) }}>{{ $slot }}</{{ $tag }}>
