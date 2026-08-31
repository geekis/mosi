@props([
    'label' => null,
    'for' => null,
    'error' => null,
])

{{-- Groups a label + control + error. Useful when a control isn't one of the built-in
     x-ui inputs (which carry their own label/error). --}}
<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <x-ui.label :for="$for">{{ $label }}</x-ui.label>
    @endif
    {{ $slot }}
    @if ($error)
        <x-ui.error :name="$error" />
    @endif
</div>
