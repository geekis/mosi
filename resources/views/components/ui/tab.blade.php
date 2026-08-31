@aware(['selected' => null, 'property' => null])

@props([
    'name' => null,
    'icon' => null,
])

@php $active = $selected !== null && $selected === $name; @endphp

<button
    type="button"
    @if ($property) wire:click="$set('{{ $property }}', '{{ $name }}')" @endif
    role="tab"
    aria-selected="{{ $active ? 'true' : 'false' }}"
    @class([
        'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors',
        'bg-white text-ink-900 shadow-soft dark:bg-white/10 dark:text-white' => $active,
        'text-taupe-600 hover:text-ink-900 dark:text-zinc-400 dark:hover:text-white' => ! $active,
    ])
>
    @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="size-4" />@endif
    {{ $slot }}
</button>
