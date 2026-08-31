@props(['for' => null])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->class('block text-sm font-medium text-ink-800 dark:text-zinc-200') }}>{{ $slot }}</label>
