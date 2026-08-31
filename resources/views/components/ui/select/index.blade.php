@props([
    'label' => null,
    'placeholder' => null,
    'name' => null,
])

{{-- Native styled select. Use <x-ui.select.option> (or plain <option>) inside. --}}
@php
    $errorKey = $name ?? $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? ($errorKey ? 'f-'.\Illuminate\Support\Str::slug(str_replace('.', '-', $errorKey)) : null);
    $hasError = $errorKey && $errors->has($errorKey);
    $forms = $name ? ['name' => $name] : [];
    $ring = $hasError
        ? 'border-coral-300 focus:border-coral-400 focus:ring-coral-200'
        : 'border-sand-300 focus:border-brand-400 focus:ring-brand-200';
    $control = 'block w-full appearance-none rounded-xl border bg-white ps-3.5 pe-10 py-2.5 text-sm text-ink-900 shadow-soft transition focus:outline-none focus:ring-2 dark:bg-white/5 dark:text-white '.$ring;
@endphp

<div class="w-full">
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="mb-1.5 block text-sm font-medium text-ink-800 dark:text-zinc-200">{{ $label }}</label>
    @endif

    <div class="relative">
        <select {{ $attributes->merge(array_merge(['id' => $id], $forms))->class($control) }} @if ($hasError) aria-invalid="true" @endif>
            @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
            {{ $slot }}
        </select>
        <x-ui.icon name="chevron-down" variant="mini" class="pointer-events-none absolute inset-y-0 end-3 my-auto size-4 text-mist-400" />
    </div>

    @if ($errorKey)
        @error($errorKey)<p class="mt-1 text-sm text-coral-600">{{ $message }}</p>@enderror
    @endif
</div>
