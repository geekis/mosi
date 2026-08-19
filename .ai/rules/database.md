# Database & models

**Globs:** `database/**`, `app/Models/**`

## Migrations

- One concern per migration, always reversible.
- MySQL 8, collation `utf8mb4_is_0900_ai_ci` (Icelandic sorting).
- Never store formatted strings for dates, money, or phone numbers.
- Index every foreign key and every column used in a feed or search filter.
- Translatable columns are JSON — see `.ai/rules/localisation.md`.

## Models

- Backed enums in `app/Enums/`, never bare strings in the database.
- `$hidden` and `$fillable` are deliberate, not copy-pasted. `kennitala` is never
  fillable and always hidden.
- `Model::preventLazyLoading()` is enabled outside production. If it throws, fix the
  query — do not disable the guard.
- Every model that users can see gets a Policy.

## The feed query

**Fan-out-on-read.** At ~260 staff, building a materialised `feed_items` table is
premature and adds a whole class of consistency bugs. Query on read:

- posts from groups the user belongs to, plus posts from people they follow
- respecting group visibility (`open` / `closed` / `secret`)
- cursor-paginated, never offset-paginated
- pinned and official posts boosted
- eager-load author, group, media, reaction counts, comment counts

Revisit only if the feed query exceeds ~200ms with realistic data volumes.

## Groups

The org-wide group ("Allir starfsmenn") is a **real group with auto-enrolment**, not a
special case. Special-casing it creates branching logic in the feed query, search,
notifications, and permissions. Keep the code uniform.

Department groups are auto-enrolled from `organizationId` in the staff sync.

## Seeding

Factories and seeders must produce realistic Icelandic data: ~260 staff, correct
patronymic name patterns, real NVS organisation names (NVS Vestursvæði, NVS
Norðurland, etc.), a mix of `entra` and `password` auth methods, and a realistic
spread of group memberships. Unrealistic seed data hides pagination, sorting, and
performance bugs until production.

## Never

- `migrate:fresh` or `db:wipe` without asking first.
- Raw SQL where Eloquent or the query builder will do.
- N+1 queries. Use `database-query` and `php artisan test` to verify counts on any
  list view.
