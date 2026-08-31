// Laravel Echo (Reverb) — sets up window.Echo for Livewire broadcast listeners.
import './echo';

// Live presence dots on avatars — subscribes to the shared presence channel. Must load
// after ./echo so window.Echo (if any) is already set up.
import './presence';

// Registers the <emoji-picker> custom element used by the post composer. It is a
// self-contained, accessible web component — no framework glue, no build config.
import 'emoji-picker-element';

// @mention autocomplete for a plain <textarea> or <input>. Typing "@" followed by a name
// opens a dropdown of matching people; picking one inserts their handle (display name
// without spaces, e.g. "@EgillHeinesen"). Used by <x-mention-textarea> for the post
// composer and every comment box, and by the chat message input.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('mentionBox', (mentionables) => ({
        people: Array.isArray(mentionables) ? mentionables : [],
        open: false,
        query: '',
        results: [],
        activeIndex: 0,
        tokenStart: -1,
        textarea: null,

        init() {
            this.textarea = this.$el.querySelector('textarea, input');

            if (!this.textarea) {
                return;
            }

            this.textarea.addEventListener('input', () => this.onInput());
            this.textarea.addEventListener('keydown', (event) => this.onKeydown(event));
            // Close on blur, but after any click on a result has had time to fire.
            this.textarea.addEventListener('blur', () => setTimeout(() => this.close(), 150));
        },

        onInput() {
            const caret = this.textarea.selectionStart;
            const before = this.textarea.value.slice(0, caret);
            const match = before.match(/(?:^|\s)@([\p{L}\p{N}._-]*)$/u);

            if (!match) {
                this.close();

                return;
            }

            this.tokenStart = caret - match[1].length - 1; // index of the "@"
            this.query = match[1].toLowerCase();
            this.results = this.people
                .filter((person) =>
                    person.handle.toLowerCase().includes(this.query) ||
                    person.name.toLowerCase().includes(this.query)
                )
                .slice(0, 8);
            this.activeIndex = 0;
            this.open = this.results.length > 0;
        },

        onKeydown(event) {
            if (!this.open) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.activeIndex = (this.activeIndex + 1) % this.results.length;
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.activeIndex = (this.activeIndex - 1 + this.results.length) % this.results.length;
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                this.select(this.results[this.activeIndex]);
            } else if (event.key === 'Escape') {
                this.close();
            }
        },

        select(person) {
            if (!person) {
                return;
            }

            const caret = this.textarea.selectionStart;
            const before = this.textarea.value.slice(0, this.tokenStart);
            const after = this.textarea.value.slice(caret);
            const inserted = '@' + person.handle + ' ';

            this.textarea.value = before + inserted + after;
            this.textarea.dispatchEvent(new Event('input', { bubbles: true }));

            const position = (before + inserted).length;
            this.textarea.focus();
            this.textarea.setSelectionRange(position, position);
            this.close();
        },

        close() {
            this.open = false;
            this.query = '';
            this.results = [];
        },
    }));
});

// Name-addressed modal used by <x-ui.modal>. Opened/closed by <x-ui.modal.trigger> and by
// Livewire via $this->dispatch('modal-show'|'modal-close', name: '…'). Handles body-scroll
// lock, initial focus and a self-contained Tab focus trap (no Alpine Focus plugin needed).
document.addEventListener('alpine:init', () => {
    const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

    window.Alpine.data('uiModal', (name, show = false) => ({
        name,
        open: false,

        init() {
            this.open = !!show;
            if (this.open) this.lock(true);
            this.$watch('open', (value) => {
                this.lock(value);
                if (value) this.$nextTick(() => this.focusFirst());
            });
        },

        lock(on) {
            document.body.style.overflow = on ? 'hidden' : '';
        },

        onShow(event) {
            if (event.detail?.name === this.name) this.open = true;
        },

        onClose(event) {
            if (!event.detail?.name || event.detail.name === this.name) this.open = false;
        },

        close() {
            this.open = false;
        },

        focusable() {
            return Array.from(this.$refs.dialog?.querySelectorAll(FOCUSABLE) ?? [])
                .filter((el) => el.offsetParent !== null);
        },

        focusFirst() {
            const els = this.focusable();
            (els[0] ?? this.$refs.dialog)?.focus();
        },

        trap(event) {
            if (event.key !== 'Tab') return;
            const els = this.focusable();
            if (!els.length) return;
            const first = els[0];
            const last = els[els.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }));

    // Transient toast stack used by <x-ui.toasts>, fed by Livewire via
    // $this->dispatch('toast', text: '…', variant: 'success'|'danger'|…).
    window.Alpine.data('uiToasts', () => ({
        items: [],
        seq: 0,

        push(detail) {
            const id = ++this.seq;
            this.items.push({
                id,
                text: detail?.text ?? '',
                variant: detail?.variant ?? 'success',
            });
            setTimeout(() => {
                this.items = this.items.filter((t) => t.id !== id);
            }, 4000);
        },
    }));

    // Tooltip for a collapsed-sidebar nav item: shows the label beside the icon rail on
    // hover, but only when the rail is actually collapsed on a desktop viewport. The label
    // element itself is teleported to <body> so the sidebar's overflow never clips it.
    window.Alpine.data('railTooltip', () => ({
        label: '',
        open: false,
        x: 0,
        y: 0,

        init() {
            this.label = this.$el.getAttribute('data-tooltip') || '';
        },

        enter() {
            const inCollapsedRail = this.$el.closest('[data-sidebar]') && this.$el.closest('[data-collapsed="true"]');
            if (! this.label || ! inCollapsedRail || ! window.matchMedia('(min-width: 1024px)').matches) {
                return;
            }
            const rect = this.$el.getBoundingClientRect();
            this.x = rect.right + 12;
            this.y = rect.top + rect.height / 2;
            this.open = true;
        },

        leave() {
            this.open = false;
        },
    }));
});
