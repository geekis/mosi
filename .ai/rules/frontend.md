# Frontend — Flux & Livewire 4

**Globs:** `resources/views/**`, `resources/css/**`, `resources/js/**`

This file overrides Boost's generic Livewire guidance, which is v3-era.

## Flux Pro first

**Never hand-build a component Flux Pro already provides.** Check the Flux docs before
writing any UI primitive.

| Need | Use |
|---|---|
| Post composer / rich text | `flux:editor` — **do not add Tiptap** |
| Global search palette | `flux:command` |
| 2FA code entry | `flux:otp-input` |
| Profile card / avatar | `flux:profile`, `flux:avatar` |
| Shell chrome | `flux:navbar`, `flux:sidebar`, `flux:dropdown` |
| Admin tables | `flux:table` + Livewire pagination |
| Events | `flux:calendar`, `flux:date-picker` |
| Feedback | `flux:toast`, `flux:modal`, `flux:skeleton` |

Customise by publishing the Blade file (`php artisan flux:publish`), not by wrapping
Flux in another abstraction layer.

Flux conflicts with `livewire/blaze` below `1.0.0-beta.2`. Keep Blaze current.

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
`flux:skeleton` placeholder.

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

WCAG 2.2 AA — an Icelandic public-sector obligation, not a nice-to-have. Flux
components are accessible by default; don't break them with custom markup. Check
keyboard navigation and visible focus states in both `is` and `en`.
