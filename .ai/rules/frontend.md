# Frontend — UI components & Livewire 4

**Globs:** `resources/views/**`, `resources/css/**`, `resources/js/**`

This file overrides Boost's generic Livewire guidance, which is v3-era.

## The `x-ui` component system

Mosi has **no** Flux dependency. The UI primitives are our own Blade components under
`resources/views/components/ui/`, used as `<x-ui.button>`, `<x-ui.input>`,
`<x-ui.modal>`, etc. Before hand-building any primitive, **check `resources/views/components/ui/`
for an existing one and reuse it** — only add a new variant the app actually needs.

| Need | Use |
|---|---|
| Buttons / links | `x-ui.button` (variants primary/secondary/filled/ghost/danger; `href` → `<a>`) |
| Form controls | `x-ui.input`, `x-ui.textarea`, `x-ui.select` + `x-ui.select.option`, `x-ui.checkbox`, `x-ui.radio`, `x-ui.switch`, `x-ui.field`, `x-ui.label`, `x-ui.error` |
| 2FA code entry | `x-ui.otp` |
| Icons | `x-ui.icon` (Heroicons via `blade-ui-kit/blade-heroicons`; `name="loading"` spins) |
| Avatar | `x-avatar` (bespoke, not a `ui` primitive) |
| Shell chrome | `x-ui.navlist` / `x-ui.navlist.item`, `x-ui.navbar`, `x-ui.dropdown`, `x-ui.menu` |
| Cards / typography | `x-ui.card`, `x-ui.heading`, `x-ui.text`, `x-ui.subheading`, `x-ui.badge`, `x-ui.separator` |
| Feedback | `x-ui.toasts` (dispatch `toast`), `x-ui.modal`, `x-ui.alert` (callout/empty-state) |

**Modals** are name-addressed: open with `<x-ui.modal.trigger name="…">` or, from PHP,
`$this->dispatch('modal-show', name: '…')` / `'modal-close'`. **Toasts** fire with
`$this->dispatch('toast', variant: '…', text: '…')`. The backing Alpine components
(`uiModal`, `uiToasts`, `railTooltip`) live in `resources/js/app.js`. The design tokens
(brand/sand/gold/coral/taupe/mist/ink ramps, `font-display`, `shadow-soft`) are in
`resources/css/app.css`.

When editing a `ui` component, keep it Livewire-safe: preserve `wire:model`/`wire:click`,
forward the attribute bag with `$attributes->merge()`/`->class()`, and never strip a
form control's `name` attribute (it is re-emitted for both the POST field and error binding).

## Traps

**`@js()` only compiles inside a native element's attribute, never inside a Blade
component tag's attribute.** `<button x-on:click="fn(@js($x))">` works; the same on
`<x-ui.select x-on:change="fn(@js($x))">` leaks the literal text `@js(...)` into the
HTML (the component tag compiler treats an unbound attribute value as a raw string).
For a value that is already safe (e.g. a `Y-m-d` date), just wrap it in single quotes:
`x-on:change="fn('{{ $day['date'] }}', …)"`. There is a regression test guarding this
on the whereabouts page.

## Livewire 4 patterns

**Single-file components** (`resources/views/components/*.wire.php`) are the default.
Extract to multi-file past roughly 150 lines. The
`@php new class extends Livewire\Component` block is for UI state and Action calls
only — business logic goes in `app/Actions/`.

**Islands** (`<wire:island>`) — isolated regions that update without re-rendering the
page. Use for the notification bell, unread chat badge, individual feed items, and
each right-rail widget.

**Lazy islands** (`<wire:island lazy>`) — the feed must paint immediately. Upcoming
events, quick links and other right-rail widgets stream in afterwards behind a
skeleton placeholder.

**Slots** (`wire:slot`) — this *is* the right-rail widget system. Use native slots; do
not invent a custom widget registry.

**`wire:sort`** — drag-and-drop ordering for quick links and nav items in admin. Do
not build sorting by hand.

**Optimistic UI** (`wire:show`) — reactions and follows register instantly. Never make
a user wait on a round trip to see their own like.

**`wire:key`** — mandatory on every item in a `@foreach`. Without stable keys,
Livewire's DOM diffing writes to the wrong row after pagination. This is the single
most common Livewire bug class.

## Structure

- Layouts → `resources/views/layouts/`
- Page components → `resources/views/pages/`
- Reusable components → `resources/views/components/`

The application shell (top nav, left nav, right rail) is a **separate layout** from
page content.

**Teams tab (V2 phase 15) keeps the full shell** — top nav and left sidebar stay
present inside the Teams webview. "Chrome-less" is *not* a requirement: the only hard
constraint for a Teams tab is that the page is iframe-embeddable (relax
`X-Frame-Options` / set CSP `frame-ancestors` for the Teams route) and calls
`app.initialize()` from the Teams JS SDK. Keep the sidebar so Groups/Events/News/Chat
navigation still works inside Teams — Teams' own chrome does not provide it. Reuse
`app.sidebar` as-is; do not build a stripped-down variant. Only caveat: test at 375px,
since Teams' mobile chrome plus our sidebar toggle can get cramped.

## Mobile

Many staff are rangers in the field on phones, and 25% have no Teams account, so Mosi
is their only channel. Every view is mobile-first. Test the feed, the composer, and
chat at 375px before considering them done.

## Accessibility

WCAG 2.2 AA — an Icelandic public-sector obligation, not a nice-to-have. The `x-ui`
components carry their own focus, ARIA and keyboard handling (modals trap focus and
close on Esc, etc.); preserve it when you edit them and don't break it with custom
markup. Check keyboard navigation and visible focus states in both `is` and `en`.
