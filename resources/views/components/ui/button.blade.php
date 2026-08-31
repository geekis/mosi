@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconTrailing' => null,
    'navigate' => true,
])

{{--
    Button / link, styled from the reference portal button. Renders an <a> when :href is
    given (with wire:navigate by default), otherwise a <button type=button>. An icon-only
    button (no slot text, just :icon) becomes a square. All Livewire/HTML attributes pass
    through the bag.
--}}
@php
    $iconOnly = $slot->isEmpty() && ($icon || $iconTrailing);

    $base = 'relative inline-flex items-center justify-center gap-2 rounded-full font-medium whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 focus-visible:ring-offset-sand-100';

    $variants = [
        'primary' => 'bg-brand-500 text-white shadow-soft hover:bg-brand-600',
        'secondary' => 'border border-sand-300 bg-white text-ink-800 shadow-soft hover:border-brand-300 hover:text-brand-700',
        'filled' => 'bg-sand-200 text-ink-800 hover:bg-sand-300',
        'ghost' => 'text-taupe-600 hover:bg-sand-200 hover:text-ink-900',
        'danger' => 'border border-coral-200 bg-white text-coral-600 hover:bg-coral-50',
    ];

    $textSizes = ['xs' => 'px-2.5 py-1 text-xs', 'sm' => 'px-3.5 py-1.5 text-sm', 'md' => 'px-5 py-2.5 text-sm', 'lg' => 'px-6 py-3 text-base'];
    $squareSizes = ['xs' => 'size-7', 'sm' => 'size-9', 'md' => 'size-10', 'lg' => 'size-12'];
    $iconSizes = ['xs' => 'size-4', 'sm' => 'size-4', 'md' => 'size-5', 'lg' => 'size-5'];

    $pad = $iconOnly ? ($squareSizes[$size] ?? $squareSizes['md']) : ($textSizes[$size] ?? $textSizes['md']);
    $iconSize = $iconSizes[$size] ?? 'size-5';
    $classes = $base.' '.$pad.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="{{ $iconSize }} shrink-0" />@endif
        {{ $slot }}
        @if ($iconTrailing)<x-ui.icon :name="$iconTrailing" variant="mini" class="{{ $iconSize }} shrink-0" />@endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="{{ $iconSize }} shrink-0" />@endif
        {{ $slot }}
        @if ($iconTrailing)<x-ui.icon :name="$iconTrailing" variant="mini" class="{{ $iconSize }} shrink-0" />@endif
    </button>
@endif
