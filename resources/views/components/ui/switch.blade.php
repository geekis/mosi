@props(['label' => null])

{{-- Pure-CSS toggle bound through the checkbox (wire:model etc. pass through). --}}
<label class="inline-flex cursor-pointer items-center gap-3">
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" {{ $attributes->class('peer sr-only') }} />
        <span class="h-6 w-11 rounded-full bg-sand-300 transition-colors peer-checked:bg-brand-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 peer-focus-visible:ring-offset-2 dark:bg-zinc-600"></span>
        <span class="absolute start-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></span>
    </span>
    @if ($label)<span class="text-sm font-medium text-ink-800 dark:text-zinc-200">{{ $label }}</span>@endif
</label>
