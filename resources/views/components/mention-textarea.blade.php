@props(['mentionables' => [], 'rows' => 3, 'placeholder' => ''])

{{--
    A plain-text textarea with @mention autocomplete. Any attributes (notably wire:model)
    are forwarded to the underlying flux:textarea. The dropdown is driven by the Alpine
    `mentionBox` component in app.js; selecting a person inserts their @handle.
--}}
<div x-data="mentionBox(@js(array_values($mentionables)))" class="relative">
    <flux:textarea :placeholder="$placeholder" rows="{{ $rows }}" {{ $attributes }} />

    <x-mention-suggestions placement="bottom" />
</div>
