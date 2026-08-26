@props(['mention', 'text', 'variant' => 'link'])

{{-- The "plain" variant just emphasises the handle in the surrounding text colour. Chat
     bubbles paint their own background (white on the sender's own primary bubble), so the
     coloured profile link of the "link" variant would fail contrast there. --}}
@if ($variant === 'plain')<span class="font-semibold">{{ $text }}</span>@else
@php
    // "Name · Job title" — the hover info. Kept to a compact line; the link goes to the
    // person's profile.
    $tip = trim($mention['name'].($mention['title'] !== '' ? ' · '.$mention['title'] : ''));
@endphp
{{-- Rendered inline inside a post/comment body. Keep the trigger markup on one line so no
     stray whitespace slips between the text and the link. --}}
<flux:tooltip :content="$tip" class="inline">@if ($mention['url'])<a href="{{ $mention['url'] }}" wire:navigate class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $text }}</a>@else<span class="font-medium text-primary-600 dark:text-primary-400">{{ $text }}</span>@endif</flux:tooltip>@endif
