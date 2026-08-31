@props(['size' => 'base'])

{{-- Body text. Muted by default; pass a text-* class to override the colour. --}}
@php
    $sizes = ['lg' => 'text-base', 'base' => 'text-sm', 'sm' => 'text-xs', 'xs' => 'text-xs'];
@endphp

<div {{ $attributes->class(['text-mist-500 dark:text-zinc-400', $sizes[$size] ?? 'text-sm']) }}>{{ $slot }}</div>
