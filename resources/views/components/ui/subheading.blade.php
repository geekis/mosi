@props(['size' => 'base'])

@php
    $sizes = ['lg' => 'text-base', 'base' => 'text-sm', 'sm' => 'text-xs'];
@endphp

<p {{ $attributes->class(['text-mist-500 dark:text-zinc-400', $sizes[$size] ?? 'text-sm']) }}>{{ $slot }}</p>
