# Authentication & authorisation

**Globs:** `app/Actions/Auth/**`, `app/Http/Middleware/**`, `app/Policies/**`,
`routes/auth.php`, `resources/views/pages/auth/**`

## Two login paths, one source of accounts

Accounts are created **only** by the staff sync (`.ai/rules/staff-api.md`). Neither
login method creates a user. There is no registration route.

- `auth_method` backed enum: `entra` | `password` | `both`
- Email domain `@nattura.is` → `entra`. Anything else → `password`.
- Roughly 25% of staff (seasonal rangers) have personal email addresses and no
  Microsoft account. They are not an edge case — design for them first.

## Identifier-first login

One email field on the login page. On submit, look up the user, then either redirect
to Microsoft or reveal the password field. Users never have to know which kind of
account they have.

**Return an identical generic response for unknown emails.** The login form must not
become a staff-directory oracle that confirms who works at NVS.

## Rules

- `is_active = false` cannot log in by any path. Check this after Entra callback too,
  not just on the password path.
- Password users are onboarded by signed, expiring invitation link. Invitations go to
  personal addresses — keep the content minimal and non-sensitive.
- If a `password` user later gains an `@nattura.is` address, link Entra to the
  **existing** record and set `auth_method = both`. Never create a second user.
- Mandatory 2FA for `super-admin` and `comms` roles.
- Rate limiting and lockout on the password path — it is the weakest link.
- Store `password_changed_at`.

## Roles (spatie/laravel-permission)

`super-admin` · `comms` · `group-owner` · `employee`

`managerFlag === 'Y'` from the staff sync auto-grants `group-owner` on the user's own
organisation group.

## Authorisation

Every model has a Policy. Every query returning user-visible content applies a
visibility scope.

Group visibility is `open` | `closed` | `secret`. A secret group must not leak into:
the feed, search results, notifications, the directory, mention autocomplete, or (in
v2) the API. **Write the leak test before the feature.**

Never rely on the UI hiding something. Scope the query.
