@props(['mention', 'text', 'variant' => 'link'])

{{-- The "plain" variant just emphasises the handle in the surrounding text colour. Chat
     bubbles paint their own background (white on the sender's own primary bubble), so the
     coloured profile link of the "link" variant would fail contrast there. --}}
@if ($variant === 'plain')<span class="font-semibold">{{ $text }}</span>@else
@php
    // "Name · Job title" — the hover title. Kept to a compact line; the link goes to the
    // person's profile.
    $tip = trim($mention['name'].($mention['title'] !== '' ? ' · '.$mention['title'] : ''));
@endphp
{{-- Rendered inline inside a post/comment body. Keep the markup on one line so no stray
     whitespace slips between the text and the link. --}}
@if ($mention['url'])<a href="{{ $mention['url'] }}" wire:navigate title="{{ $tip }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $text }}</a>@else<span title="{{ $tip }}" class="font-medium text-brand-600 dark:text-brand-400">{{ $text }}</span>@endif@endif
