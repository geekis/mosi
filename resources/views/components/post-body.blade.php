@props(['body', 'mentions' => [], 'variant' => 'link'])

@php
    // Split the plain-text body into runs of text and @handle tokens. The negative
    // look-behind stops mid-word matches like an email's "name@host" from being treated
    // as a mention.
    $segments = preg_split('/((?<![\p{L}\p{N}._-])@[\p{L}\p{N}._-]+)/u', (string) $body, -1, PREG_SPLIT_DELIM_CAPTURE);
@endphp

{{-- Text runs are escaped and line breaks preserved with <br>; a token whose handle
     resolves to a known person becomes a linked mention with a hover card. Everything is
     emitted tight so no whitespace is injected between runs. --}}
<div {{ $attributes }}>@foreach ($segments as $segment)@php($handle = str_starts_with($segment, '@') ? mb_strtolower(ltrim($segment, '@')) : null)@if ($handle !== null && isset($mentions[$handle]))<x-mention-chip :mention="$mentions[$handle]" :text="$segment" :variant="$variant" />@else{!! nl2br(e($segment)) !!}@endif@endforeach</div>
