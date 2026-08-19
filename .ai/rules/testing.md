# Testing

**Globs:** `tests/**`

Pest 4. Create with `php artisan make:test --pest SomeFeatureTest`. Run with
`php artisan test --compact`, filtered where possible.

## What must always have a test

- **Every Action class.** No exceptions.
- **Every authorisation rule.** Write the test *before* the implementation.
- **Group visibility leaks.** For each surface — feed, search, directory, mention
  autocomplete, notifications — assert that a `secret` group's content is invisible to
  a non-member. This is the highest-value test suite in the project.
- **Both login paths**, independently, plus the case where a `password` user later
  gains an Entra account and must link to the existing record rather than duplicate.
- **The staff sync**: idempotency, the `4712-12-31` sentinel, the circuit breaker
  (abort on a >15% row drop), reactivation of a returning seasonal ranger with content
  intact, and the mixed int/string `assignmentNumber`.

## Browser tests

Pest 4 browser tests for the feed, the post composer, and both login paths. These are
the flows where a Livewire island or `wire:key` bug is invisible to feature tests.

## Conventions

- Use factories, and check for existing custom states before setting fields manually.
- Realistic Icelandic seed data — see `.ai/rules/database.md`. Testing with
  "User 1, User 2" hides sorting and pagination bugs.
- Assert query counts on list views to catch N+1 regressions.
- Do **not** delete tests without approval.
- Do not write throwaway verification scripts or tinker snippets when a test would
  prove the same thing.

## Locale

Feature tests for notifications and mailables must assert the **recipient's** locale
is used, not the app default. See `.ai/rules/localisation.md`.
