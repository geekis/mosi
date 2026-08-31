@props([
    'color' => 'zinc',
    'size' => 'sm',
])

@php
    $colors = [
        'zinc' => 'bg-sand-200 text-ink-700',
        'green' => 'bg-brand-100 text-brand-700',
        'brand' => 'bg-brand-100 text-brand-700',
        'red' => 'bg-coral-100 text-coral-700',
        'gold' => 'bg-gold-100 text-gold-800',
        'sky' => 'bg-sky-portal-100 text-sky-portal-700',
    ];
    $sizes = ['sm' => 'px-2 py-0.5 text-xs', 'md' => 'px-2.5 py-1 text-sm'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full font-medium', $colors[$color] ?? $colors['zinc'], $sizes[$size] ?? $sizes['sm']]) }}>
    {{ $slot }}
</span>
