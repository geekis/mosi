@props(['heading', 'icon' => null])

{{--
    A right-rail widget placeholder. The real widgets (birthdays, events, new
    colleagues) are delivered by later phases (directory §6, events §7); until then
    this renders a titled card with a shimmering skeleton body so the shell layout
    is complete and honest about what is coming.
--}}
<x-ui.card {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <div class="flex items-center gap-2">
        @if ($icon)
            <x-ui.icon :name="$icon" variant="mini" class="text-brand-500 dark:text-brand-400" />
        @endif
        <x-ui.heading size="sm">{{ $heading }}</x-ui.heading>
    </div>

    <div class="space-y-2" aria-hidden="true">
        <div class="h-3 w-3/4 animate-pulse rounded bg-sand-200 dark:bg-zinc-700"></div>
        <div class="h-3 w-1/2 animate-pulse rounded bg-sand-200 dark:bg-zinc-700"></div>
        <div class="h-3 w-2/3 animate-pulse rounded bg-sand-200 dark:bg-zinc-700"></div>
    </div>
</x-ui.card>
