@props([
    'href' => '#',
    'navigate' => false,
])

<a href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes->class('font-medium text-brand-700 underline-offset-2 hover:underline dark:text-brand-400') }}>{{ $slot }}</a>
