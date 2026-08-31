@props([
    'label' => null,
    'description' => null,
    'icon' => null,
    'type' => 'text',
    'size' => 'md',
    'name' => null,
    'viewable' => false,
])

{{--
    Text-style input with an optional built-in label, description and validation error.
    The error key comes from the `name` prop (plain forms) or wire:model (Livewire). When
    `name` is given it is re-emitted as the input's name attribute so normal form POSTs
    keep working. `viewable` adds a show/hide toggle for password fields.
--}}
@php
    $errorKey = $name ?? $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? ($errorKey ? 'f-'.\Illuminate\Support\Str::slug(str_replace('.', '-', $errorKey)) : null);
    $hasError = $errorKey && $errors->has($errorKey);
    $forms = $name ? ['name' => $name] : [];
    $canView = $viewable && $type === 'password';

    $sizePad = ['sm' => 'py-1.5 text-sm', 'md' => 'py-2.5 text-sm', 'lg' => 'py-3 text-base'][$size] ?? 'py-2.5 text-sm';
    $ring = $hasError
        ? 'border-coral-300 focus:border-coral-400 focus:ring-coral-200'
        : 'border-sand-300 focus:border-brand-400 focus:ring-brand-200';

    if ($type === 'file') {
        $control = 'block w-full cursor-pointer rounded-xl border border-sand-300 bg-white p-2 text-sm text-ink-700 shadow-soft file:me-3 file:rounded-full file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:bg-white/5 dark:text-zinc-200';
    } else {
        $control = 'block w-full rounded-xl border bg-white text-ink-900 placeholder:text-mist-400 shadow-soft transition focus:outline-none focus:ring-2 '
            .($icon ? 'ps-10 ' : 'ps-3.5 ').($canView ? 'pe-11 ' : 'pe-3.5 ').$sizePad.' '.$ring.' dark:bg-white/5 dark:text-white';
    }
@endphp

<div class="w-full">
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="mb-1.5 block text-sm font-medium text-ink-800 dark:text-zinc-200">{{ $label }}</label>
    @endif

    <div class="relative" @if ($canView) x-data="{ show: false }" @endif>
        @if ($icon && $type !== 'file')
            <x-ui.icon :name="$icon" variant="mini" class="pointer-events-none absolute inset-y-0 start-3 my-auto size-5 text-mist-400" />
        @endif

        <input
            {{ $attributes->merge(array_merge(['id' => $id, 'type' => $type], $forms))->class($control) }}
            @if ($canView) x-bind:type="show ? 'text' : 'password'" @endif
            @if ($hasError) aria-invalid="true" @endif
        />

        @if ($canView)
            <button type="button" x-on:click="show = ! show" tabindex="-1"
                class="absolute inset-y-0 end-0 flex items-center pe-3 text-mist-400 hover:text-ink-700"
                :aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))">
                <x-ui.icon name="eye" variant="mini" x-show="! show" class="size-5" />
                <x-ui.icon name="eye-slash" variant="mini" x-show="show" x-cloak class="size-5" />
            </button>
        @endif
    </div>

    @if ($description)
        <p class="mt-1 text-xs text-mist-500">{{ $description }}</p>
    @endif
    @if ($errorKey)
        @error($errorKey)<p class="mt-1 text-sm text-coral-600">{{ $message }}</p>@enderror
    @endif
</div>
