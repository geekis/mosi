@props(['heading', 'icon' => null])

{{--
    A right-rail widget placeholder. The real widgets (birthdays, events, new
    colleagues) are delivered by later phases (directory §6, events §7); until then
    this renders a titled card with a shimmering skeleton body so the shell layout
    is complete and honest about what is coming.
--}}
<flux:card {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
        @if ($icon)
            <flux:icon :icon="$icon" variant="mini" />
        @endif
        <flux:heading size="sm">{{ $heading }}</flux:heading>
    </div>

    <div class="space-y-2" aria-hidden="true">
        <div class="h-3 w-3/4 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
        <div class="h-3 w-1/2 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
        <div class="h-3 w-2/3 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
    </div>
</flux:card>
