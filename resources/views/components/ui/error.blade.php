@props(['name' => null])

{{-- Validation error for a field; reads Livewire's error bag. --}}
@if ($name)
    @error($name)
        <p {{ $attributes->class('mt-1 text-sm text-coral-600') }}>{{ $message }}</p>
    @enderror
@endif
