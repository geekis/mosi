@props(['size' => null])

{{--
    Surface card in the reference style: rounded-3xl, sand border, warm soft shadow.
    Padding and radius use low-specificity `[:where(&)]` so callers can override them
    (e.g. `class="p-0 overflow-hidden"` for a full-bleed banner).
--}}
<div {{ $attributes->class([
    'border border-sand-200/80 bg-white shadow-soft dark:border-white/10 dark:bg-white/5',
    '[:where(&)]:rounded-3xl',
    '[:where(&)]:p-4' => $size === 'sm',
    '[:where(&)]:p-6' => $size !== 'sm',
]) }}>
    {{ $slot }}
</div>
