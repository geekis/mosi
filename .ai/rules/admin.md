# Filament admin panel

**Globs:** `app/Filament/**`

Filament **5**, not 4 — Filament 4 does not support Livewire 4. Much published
Filament example code targets v4 or v3. Use `search-docs` rather than recalled syntax.

## Rules

- Panel path `/admin`, branded "Mosi Admin".
- Resources call the same Action classes as the front end (`app/Actions/**`). Never
  duplicate business logic into a Filament resource.
- Every resource is authorised by the model's Policy. Filament access is not a
  substitute for a Policy.
- `super-admin` and `comms` reach the panel; `group-owner` gets scoped access to
  their own groups only; `employee` never.

## Never expose

`kennitala` must not appear in any Filament table, infolist, form, filter, export, or
global search result. Same for anything derived from it, including date of birth.

## What belongs in the panel

Users (view, invite, resend invitation, deactivate — but **never** create, since the
staff sync owns account creation), groups, events, quick links, nav items, static
pages, documents, roles, and the staff sync run log with a manual trigger.

Translatable models need translation editing per locale in their forms — see
`.ai/rules/localisation.md`.

## Icelandic

Filament ships partial Icelandic translations. Fill gaps in
`lang/vendor/filament/is/`. Admin users are Icelandic-speaking staff, so the panel
being half-English is a real usability problem, not a cosmetic one.
