# Phase 1 — Identity & staff sync

**Branch:** `phase-1-identity`
**Rules to read:** `.ai/rules/staff-api.md`, `.ai/rules/auth.md`,
`.ai/rules/database.md`, `.ai/rules/testing.md`

**Goal:** you can log in with a work account or an invited password account. Nobody
else can.

**Blocked until answered** (see `.ai/phases/index.md`): the `personType` and
`assignmentStatus` enums from HR, and whether departed staff vanish from the API.
Don't start 1B without these — activation logic depends on them.

This is the largest phase in the project. Run it as four sessions, each committing
before the next starts.

---

## 1A — Schema & user model

> Read `.ai/rules/staff-api.md` and `.ai/rules/database.md` first.
>
> Build the schema for Mosi's users, mapped to the NVS staff API. The field mapping,
> the kennitala rules, and the `4712-12-31` sentinel are all in the rule file — follow
> them exactly.
>
> - Extend the `User` model per the mapping table
> - `kennitala` encrypted with a `kennitala_hash` SHA-256 lookup column; never
>   fillable, always hidden
> - `AuthMethod`, `PersonType`, `AssignmentStatus` backed enums in `app/Enums/`
> - `staff_sync_runs` table: counts of created/updated/deactivated/reactivated, plus
>   errors and timing
> - Spatie permission tables; seeder for the four roles
> - A `UserFactory` producing realistic Icelandic data — correct patronymic name
>   patterns, a mix of `@nattura.is` and personal emails, real NVS org names
> - A seeder generating ~260 staff across the real organisations
>
> Write tests proving kennitala never appears in `toArray()`, JSON serialisation, or
> logs. Commit locally.

## 1B — The sync

> Read `.ai/rules/staff-api.md` again. Build `SyncStaffFromApi` in
> `app/Actions/Staff/`, plus the `app:sync-staff` command and hourly schedule entry.
>
> Write the tests first. They must cover:
> - Idempotency — running twice changes nothing the second time
> - `4712-12-31` normalises to `null`
> - `assignmentNumber` as both integer and string
> - `gsm_phone` integer → `+354…` string
> - Only the `primaryFlag === 'Y'` assignment is used
> - **Circuit breaker:** a payload >15% smaller than the last successful run aborts,
>   logs, notifies admins, and changes nothing
> - Absence → `is_active = false`, never deleted
> - A returning seasonal ranger reactivates with posts and memberships intact
> - `supervisorPersonId` resolves to `users.id` in a second pass
> - `auth_method` derived from email domain
>
> Use a fixture based on the real payload shape. Commit locally.

## 1C — Entra SSO

> Read `.ai/rules/auth.md`. Wire up Socialite with the Microsoft Azure provider.
>
> - Login rejected unless a matching active staff record exists — SSO does not create
>   users
> - `is_active = false` rejected **after** the Entra callback, not just before
> - A `password` user gaining an `@nattura.is` address links to the **existing**
>   record and sets `auth_method = both`; never duplicates
> - Tell me which redirect URIs to register in Entra. Do not attempt it yourself.
>
> Test the linking case explicitly — it's the one that silently creates duplicate
> accounts. Commit locally.

## 1D — Password path & invitations

> Read `.ai/rules/auth.md` and `.ai/rules/frontend.md`.
>
> - Identifier-first login: one email field, then either redirect to Microsoft or
>   reveal the password field. **Identical generic response for unknown emails** —
>   test that the form can't be used to enumerate staff
> - Invitation flow: signed expiring link, set-password page, resend
> - Password reset for `password` users only
> - 2FA mandatory for `super-admin` and `comms`, using `flux:otp-input`
> - Rate limiting and lockout on the password path
> - Both login pages fully translated, `is` and `en`
>
> Pest browser tests for both paths end to end. Commit locally.

---

## Acceptance criteria

- [ ] `php artisan app:sync-staff` populates users from a fixture
- [ ] Circuit breaker aborts on a shrunken payload and changes nothing
- [ ] Seasonal ranger reactivation preserves content
- [ ] Entra login works; inactive and unknown users rejected
- [ ] Password login works via invitation; unknown emails give a generic response
- [ ] Entra linking to an existing password account creates no duplicate
- [ ] 2FA enforced for privileged roles
- [ ] Kennitala absent from every serialisation, log, and response
- [ ] Both login pages render correctly in `is` and `en`, and at 375px
- [ ] Pint, Larastan, and the full suite clean

## Notes

The invitation email goes to personal addresses (gmail, live.com). Keep the content
minimal — no kennitala, no job details, no org structure.

If HR's `assignmentStatus` list reveals states that aren't clearly active or inactive,
stop and ask rather than guessing. Guessing here means either locking out real staff
or admitting people who've left.
