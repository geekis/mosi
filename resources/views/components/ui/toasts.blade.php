{{-- Global toast stack. Fed by Livewire via $this->dispatch('toast', text: '…',
     variant: 'success'|'danger'|'warning'). Place once per layout inside @persist. --}}
<div
    x-data="uiToasts()"
    x-on:toast.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4"
>
    <template x-for="t in items" :key="t.id">
        <div
            x-show="true"
            x-transition
            role="status"
            class="pointer-events-auto flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium shadow-soft-lg"
            :class="{
                'border-brand-200 bg-brand-50 text-brand-800': t.variant === 'success',
                'border-coral-200 bg-coral-50 text-coral-800': t.variant === 'danger',
                'border-gold-200 bg-gold-50 text-gold-800': t.variant === 'warning',
                'border-sand-200 bg-white text-ink-800': ['success','danger','warning'].indexOf(t.variant) === -1,
            }"
        >
            <span x-text="t.text"></span>
        </div>
    </template>
</div>
