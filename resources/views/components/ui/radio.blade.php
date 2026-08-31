@props(['label' => null])

<label class="inline-flex items-start gap-2 text-sm text-ink-800 dark:text-zinc-200">
    <input type="radio" {{ $attributes->class('mt-0.5 size-4 border-sand-300 text-brand-500 shadow-soft focus:ring-2 focus:ring-brand-200 focus:ring-offset-0') }} />
    @if ($label)<span>{{ $label }}</span>@endif
    {{ $slot }}
</label>
