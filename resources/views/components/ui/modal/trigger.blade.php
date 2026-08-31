@props(['name' => null])

{{-- Opens the named modal. `display:contents` so it doesn't affect layout. --}}
<div {{ $attributes->class('contents') }} x-on:click="$dispatch('modal-show', { name: @js($name) })">{{ $slot }}</div>
