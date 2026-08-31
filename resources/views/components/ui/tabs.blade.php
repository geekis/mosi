@props([
    'selected' => null,
    'property' => null,
])

{{-- Segmented tab control. Children (<x-ui.tab>) read `selected`/`property` via @aware and
     set the Livewire property on click. --}}
<div {{ $attributes->class('inline-flex items-center gap-1 rounded-full border border-sand-200 bg-sand-100 p-1 dark:border-white/10 dark:bg-white/5') }} role="tablist">
    {{ $slot }}
</div>
