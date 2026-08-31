@props([
    'icon' => null,
    'variant' => 'info',
])

{{-- Callout / empty-state box, used mostly for empty states and inline notices. --}}
@php
    $variants = [
        'info' => 'border-sand-200 bg-sand-50 text-ink-700',
        'success' => 'border-brand-200 bg-brand-50 text-brand-800',
        'warning' => 'border-gold-200 bg-gold-50 text-gold-800',
        'danger' => 'border-coral-200 bg-coral-50 text-coral-800',
    ];
@endphp

<div {{ $attributes->class(['flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm', $variants[$variant] ?? $variants['info']]) }} role="status">
    @if ($icon)
        <x-ui.icon :name="$icon" variant="mini" class="mt-0.5 size-5 shrink-0 opacity-70" />
    @endif
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
