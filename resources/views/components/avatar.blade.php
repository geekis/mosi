@props([
    'user' => null,
    'src' => null,
    'initials' => null,
    'name' => null,
    'icon' => null,
    'size' => 'sm',
    'presence' => true,
])

{{--
    A fully custom avatar so the styling is ours to control: an exact
    pixel circle showing a photo, or a deterministic tinted circle with white display-font
    initials (or an icon) when there is none. A live presence dot is overlaid for a $user.

    Identity comes from a User model, or is passed explicitly (groups, reaction rows, an
    upload preview) via name / initials / src / icon. Layout classes (ring-*, mt-*, …) go
    on the outer element through the attribute bag.
--}}
@php
    // An empty avatar URL counts as "no photo".
    $src = ($src ?? $user?->avatarUrl()) ?: null;
    $initials = $initials ?? $user?->initials();
    $name = $name ?? $user?->display_name;

    // Named sizes → an exact pixel box, the initials font size, and the icon size. Keyed
    // to the presence-dot sizes so the overlaid dot stays proportional.
    [$px, $textClass, $iconClass] = match ($size) {
        'xs' => [24, 'text-[0.625rem]', 'size-3.5'],
        'lg' => [48, 'text-base', 'size-6'],
        'xl' => [64, 'text-xl', 'size-8'],
        default => [32, 'text-xs', 'size-4'],
    };

    // A photo wins; otherwise a deterministic tint keeps each identity a stable colour.
    $hasPhoto = $src !== null;
    $tints = ['bg-brand-500', 'bg-gold-500', 'bg-coral-500', 'bg-sky-portal-400', 'bg-taupe-500', 'bg-mist-400'];
    $tint = $tints[crc32((string) ($user?->id ?? $name ?? $initials ?? '')) % count($tints)];

    // Fall back to an icon when there are no initials to show (e.g. a group with no photo).
    $glyph = $icon ?? (($initials === null || $initials === '') ? 'user' : null);
@endphp

{{--
    Outer wrapper carries the ring/layout classes and the presence dot. The inner circle
    clips the image with overflow-hidden (a crisp edge, flush with the ring) rather than a
    rounded <img> — whose anti-aliased edge otherwise leaves a faint hairline against the
    ring. The dot lives on the outer wrapper so it is never clipped.
--}}
<span {{ $attributes->class('relative inline-flex shrink-0 rounded-full') }}>
    <span
        @class(['flex items-center justify-center overflow-hidden rounded-full', $tint => ! $hasPhoto])
        style="width: {{ $px }}px; height: {{ $px }}px;"
        @if ($name) title="{{ $name }}" @endif
    >
        @if ($hasPhoto)
            <img src="{{ $src }}" alt="{{ $name }}" class="size-full object-cover" loading="lazy" />
        @elseif ($glyph)
            <x-ui.icon :name="$glyph" class="{{ $iconClass }} text-white" />
        @else
            <span class="font-display font-medium leading-none text-white {{ $textClass }}">{{ $initials }}</span>
        @endif
    </span>

    @if ($presence && $user)
        <x-presence-dot :user="$user" :size="$size" />
    @endif
</span>
