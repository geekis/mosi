@props(['group', 'height' => 'h-20'])

{{--
    A group's banner strip, capped at 80px tall. Groups without an uploaded banner get a
    deterministic tinted placeholder — the palette is picked from the group id so each
    group keeps a stable, distinct colour rather than a single flat gradient.
--}}
@php($bannerUrl = $group->bannerUrl())

@php($palettes = [
    'from-primary-500 to-primary-700',
    'from-emerald-500 to-teal-700',
    'from-sky-500 to-indigo-700',
    'from-amber-500 to-orange-700',
    'from-rose-500 to-pink-700',
    'from-violet-500 to-purple-700',
])

@if ($bannerUrl)
    <img src="{{ $bannerUrl }}" alt="" data-group-banner="image"
        {{ $attributes->merge(['class' => $height.' w-full object-cover']) }} />
@else
    @php($palette = $palettes[crc32((string) $group->id) % count($palettes)])
    <div data-group-banner="placeholder"
        {{ $attributes->merge(['class' => 'relative '.$height.' w-full overflow-hidden bg-gradient-to-br '.$palette]) }}>
        <flux:icon icon="user-group" class="absolute -bottom-3 -end-2 size-20 text-white/20" />
    </div>
@endif
