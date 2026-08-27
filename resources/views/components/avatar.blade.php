@props([
    'user',
    'size' => 'sm',
    'presence' => true,
    'icon' => null,
    'name' => null,
])

{{--
    A user avatar with a live presence dot overlaid. Wraps flux:avatar in a relative box so
    the dot can sit bottom-right. Layout classes (mt-*, shrink-0, ring-*) belong on this
    wrapper via the attribute bag; avatar identity comes from the $user. Pass presence=false
    for avatars that should not carry a dot (e.g. the current user's own composer avatar).
--}}
<span {{ $attributes->class('relative inline-flex shrink-0') }}>
    <flux:avatar
        :name="$name ?? $user?->display_name"
        :initials="$user?->initials()"
        :src="$user?->avatarUrl()"
        :icon="$icon"
        :size="$size"
    />

    @if ($presence && $user)
        <x-presence-dot :user="$user" :size="$size" />
    @endif
</span>
