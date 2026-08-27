@props(['entry' => null, 'reveal' => false])

{{--
    Renders a single whereabouts registration: a work location by name, or an absence.
    An absence shows as the generic "Fjarverandi" unless $reveal is set (the viewer is the
    person themselves), which surfaces the real reason. Renders nothing when there is no
    registration, so callers can drop it in unconditionally.
--}}
@if ($entry)
    @php($absent = $entry->isAbsence())
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-sm']) }}>
        <flux:icon :icon="$absent ? 'minus-circle' : 'map-pin'" variant="micro"
            class="{{ $absent ? 'text-amber-500' : 'text-primary-600 dark:text-primary-400' }}" />
        <span class="{{ $absent ? 'text-zinc-500 dark:text-zinc-400' : '' }}">{{ $entry->statusLabel($reveal) }}</span>
    </span>
@endif
