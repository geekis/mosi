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
