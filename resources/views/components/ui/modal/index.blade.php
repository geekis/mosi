@props([
    'name' => null,
    'show' => false,
])

{{--
    Name-addressed modal. Open/close via <x-ui.modal.trigger name="…">,
    <x-ui.modal.close>, or from Livewire with
    $this->dispatch('modal-show'|'modal-close', name: '…'). Handles scroll-lock, initial
    focus, a Tab focus-trap, Esc and click-outside (see uiModal in app.js).
    Width defaults to max-w-lg but any max-w-* class you pass wins.
--}}
<div
    x-data="uiModal(@js($name), @js((bool) $show))"
    x-on:modal-show.window="onShow($event)"
    x-on:modal-close.window="onClose($event)"
    x-on:keydown.escape.window="open && close()"
    x-cloak
>
    <div
        x-show="open"
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink-950/40 p-4 pt-[8vh] backdrop-blur-sm"
        x-on:click.self="close()"
        x-on:keydown="trap($event)"
    >
        <div
            x-show="open"
            x-transition
            x-ref="dialog"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            {{ $attributes->class([
                'relative w-full rounded-3xl border border-sand-200/80 bg-white p-6 shadow-soft-lg focus:outline-none dark:border-white/10 dark:bg-zinc-900',
                '[:where(&)]:max-w-lg',
            ]) }}
        >
            {{ $slot }}
        </div>
    </div>
</div>
