@props([
    'sidebar' => false,
])

{{-- Brand mark + wordmark. Renders as a link; href/wire:navigate pass through. --}}
<a {{ $attributes->class('flex items-center gap-2') }}>
    <span class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-white">
        <x-app-logo-icon class="size-5 fill-current text-white" />
    </span>
    <span class="truncate font-display text-base font-medium tracking-tight text-ink-900 group-data-[collapsed=true]/shell:lg:hidden dark:text-white">{{ config('app.name', 'Mosi') }}</span>
</a>
