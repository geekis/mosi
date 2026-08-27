@props(['user'])

@php
    use App\Enums\PresenceStatus;

    $status = app(\App\Actions\Presence\ResolvePresence::class)($user);

    // A heartbeat-only user (no Teams presence) reads more naturally as "Active now" or
    // "Last active …" than the Teams state labels; a Graph-backed user keeps its real state.
    $graphBacked = $user->entra_oid !== null && config('services.azure.graph.enabled');
    $initialLabel = match (true) {
        ! $graphBacked && $status === PresenceStatus::Available => __('Active now'),
        ! $graphBacked && $status === PresenceStatus::Offline && $user->last_active_at !== null => __('Last active :time', ['time' => $user->last_active_at->diffForHumans()]),
        default => $status->label(),
    };
@endphp

<span
    x-data="presenceLine({{ $user->id }}, @js($status->value), @js($initialLabel))"
    {{ $attributes->class('inline-flex items-center gap-1.5 text-xs') }}
>
    <span
        class="size-2 shrink-0 rounded-full {{ [
            'available' => 'bg-green-500',
            'away' => 'bg-amber-500',
            'busy' => 'bg-red-500',
            'offline' => 'bg-zinc-300 dark:bg-zinc-600',
        ][$status->value] }}"
        :class="{
            'bg-green-500': status === 'available',
            'bg-amber-500': status === 'away',
            'bg-red-500': status === 'busy',
            'bg-zinc-300 dark:bg-zinc-600': status === 'offline',
        }"
    ></span>
    <span class="text-zinc-500 dark:text-zinc-400" x-text="label">{{ $initialLabel }}</span>
</span>
