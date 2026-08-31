@props([
    'label' => null,
    'description' => null,
    'rows' => 3,
    'name' => null,
])

@php
    $errorKey = $name ?? $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? ($errorKey ? 'f-'.\Illuminate\Support\Str::slug(str_replace('.', '-', $errorKey)) : null);
    $hasError = $errorKey && $errors->has($errorKey);
    $forms = $name ? ['name' => $name] : [];
    $ring = $hasError
        ? 'border-coral-300 focus:border-coral-400 focus:ring-coral-200'
        : 'border-sand-300 focus:border-brand-400 focus:ring-brand-200';
    $control = 'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-ink-900 placeholder:text-mist-400 shadow-soft transition focus:outline-none focus:ring-2 dark:bg-white/5 dark:text-white '.$ring;
@endphp

<div class="w-full">
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="mb-1.5 block text-sm font-medium text-ink-800 dark:text-zinc-200">{{ $label }}</label>
    @endif

    <textarea {{ $attributes->merge(array_merge(['id' => $id, 'rows' => $rows], $forms))->class($control) }} @if ($hasError) aria-invalid="true" @endif>{{ $slot }}</textarea>

    @if ($description)
        <p class="mt-1 text-xs text-mist-500">{{ $description }}</p>
    @endif
    @if ($errorKey)
        @error($errorKey)<p class="mt-1 text-sm text-coral-600">{{ $message }}</p>@enderror
    @endif
</div>
