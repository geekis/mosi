@props(['user', 'size' => 'sm'])

@php
    $status = app(\App\Actions\Presence\ResolvePresence::class)($user)->value;

    // Dot size keyed to the avatar it overlays.
    $dotSize = match ($size) {
        'xs' => 'size-2',
        'sm' => 'size-2.5',
        'lg' => 'size-3',
        'xl' => 'size-3.5',
        default => 'size-2.5',
    };

    $initialFill = [
        'available' => 'bg-green-500',
        'away' => 'bg-amber-500',
        'busy' => 'bg-red-500',
        'offline' => 'bg-zinc-300 dark:bg-zinc-600',
    ][$status];
@endphp

@once
    {{-- One-time global: localised labels for dot tooltips, read by presence.js. --}}
    <script data-presence-labels>
        window.__presenceLabels = @js(collect(\App\Enums\PresenceStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]));
    </script>
    {{-- Safelist every dot fill so Tailwind keeps them even when none is rendered now. --}}
    <span class="hidden bg-green-500 bg-amber-500 bg-red-500 bg-zinc-300 dark:bg-zinc-600"></span>
@endonce

<span
    x-data="presenceDot({{ $user->id }}, @js($status))"
    role="img"
    :aria-label="label"
    :title="label"
    {{ $attributes->class(['absolute bottom-0 right-0 block rounded-full border-2 border-white dark:border-zinc-800', $dotSize, $initialFill]) }}
    :class="{
        'bg-green-500': status === 'available',
        'bg-amber-500': status === 'away',
        'bg-red-500': status === 'busy',
        'bg-zinc-300 dark:bg-zinc-600': status === 'offline',
    }"
></span>
