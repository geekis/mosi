@props([
    'variant' => 'default',
    'vertical' => false,
])

@php
    $color = $variant === 'subtle' ? 'border-sand-100 dark:border-white/5' : 'border-sand-200 dark:border-white/10';
@endphp

@if ($vertical)
    <div {{ $attributes->class(['w-px self-stretch border-l', $color]) }} role="separator" aria-orientation="vertical"></div>
@else
    <div {{ $attributes->class(['border-t', $color]) }} role="separator"></div>
@endif
