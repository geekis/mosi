{{-- Closes the enclosing <x-ui.modal> (reads `open` from its Alpine scope). --}}
<span {{ $attributes->class('contents') }} x-on:click="open = false">{{ $slot }}</span>
