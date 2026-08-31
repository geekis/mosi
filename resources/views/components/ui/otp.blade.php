@props(['digits' => 6])

{{-- One-time-code input. A single centred field (accessible, native paste + autofill),
     bound through wire:model like any input. --}}
<input
    type="text"
    inputmode="numeric"
    autocomplete="one-time-code"
    maxlength="{{ $digits }}"
    {{ $attributes->class('block w-full rounded-2xl border border-sand-300 bg-white px-4 py-3 text-center font-display text-2xl tracking-[0.4em] text-ink-900 shadow-soft placeholder:tracking-normal placeholder:text-mist-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:bg-white/5 dark:text-white') }}
/>
