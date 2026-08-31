# Staff API

**Globs:** `app/Actions/Staff/**`, `app/Models/User.php`,
`app/Console/Commands/SyncStaff*`, `database/migrations/*users*`,
`database/migrations/*staff*`

The NVS staff API is the sole source of truth for who exists. Read-only. The app
never creates users any other way — **there is no self-registration.**

## Field mapping

| API field | Column | Notes |
|---|---|---|
| `personId` | `external_id` | Stable public key. Use this everywhere user-facing. |
| `nationalIdentifier` | `kennitala` (encrypted) + `kennitala_hash` | Sensitive — see below. |
| `employeeName` | `display_name` | Full name, one string. Derive `given_name` = first token. |
| `email` | `email` | Personal (gmail/live) or `@nattura.is`. Drives `auth_method`. |
| `gsm_phone` | `phone` | **Integer in the API.** Cast to string, normalise to `+354…`. |
| `employeeNumber` | `employee_number` | Cast to string. |
| `nationality` | — | Default locale hint only: `IS` → `is`, else `en`. User may override. |
| `sex` | — | **Do not store.** No product use, GDPR liability. |
| `personType` | `person_type` | `"Starfsmaður"` seen. Full enum unconfirmed — ask before assuming. |

Primary assignment only (`primaryFlag === 'Y'`):

| API field | Column | Notes |
|---|---|---|
| `jobName` / `jobId` | `job_title` / `job_id` | e.g. `"Landvörður"` |
| `organizationId` / `organizationName` | same | Drives department group auto-enrolment |
| `parentOrganizationId` | same | `39162` = NVS root → org-wide group |
| `assignmentStatus` | same | `"Virkt starf"` seen. Full enum unconfirmed. |
| `assignmentStartDate` | `assignment_starts_at` | May be future-dated |
| `assignmentEndDate` | `assignment_ends_at` | **`4712-12-31` is an Oracle sentinel → store `null`.** |
| `assignmentPercent` | `assignment_percent` | |
| `managerFlag` | `is_manager` | `"Y"` → grant `group-owner` on their org group |
| `supervisorPersonId` | `supervisor_external_id` | Resolve to `users.id` in a second pass |
| `location*` | `location_*` | **Data quality suspect** — Norðurland staff show a Hvolsvöllur address. Do not build directory filters on location without verifying. |

## Kennitala — strict

Icelandic national ID. Sensitive personal data under GDPR, and it encodes date of
birth (`010299` = 1 Feb 1999).

- Encrypted at rest (`encrypted` cast), with a SHA-256 `kennitala_hash` for lookups.
- Never in an API response, URL, query string, log line, exception message, Filament
  table, or export.
- Excluded from `$fillable`, listed in `$hidden`.

### Birthday — confirmed (product decision, Aug 2026)

Staff birthdays are shown org-wide (the feed's "Afmæli framundan" celebrations widget).
Only **month and day** are ever derived, stored, or displayed — **never the year**, so
no age is exposed.

- `birth_month` / `birth_day` (tinyint, nullable) are derived from the kennitala in the
  `User::kennitala()` mutator, so the sync populates them for free and they stay in
  lockstep with the encrypted value. See `User::birthdayFromKennitala()`.
- The kennitala itself is **never** decrypted to render a birthday — the widget reads
  the month/day columns. A company kennitala (day + 40) has no birthday and stays null.
- This supersedes the earlier "birthdays must be opt-in" note. If per-person opt-out is
  wanted later, add a boolean and filter the celebrations query on it.

## Sync rules

- `app:sync-staff`, scheduled hourly, plus on demand from Filament.
- Paginate with `rowOffset` / `fetchSize`; honour `totalRows`.
- **Circuit breaker:** if `totalRows` is more than 15% below the last successful sync,
  abort, log, notify admins, and change nothing. A partial API failure must never
  deactivate the workforce.
- Match on `external_id` first, `kennitala_hash` second.
- Absence from the payload → `is_active = false`. **Never hard-delete.** Seasonal
  rangers return under the same `personId` and must reactivate with their posts,
  comments and group memberships intact.
- `assignmentNumber` is sometimes an integer (`172239`), sometimes a string
  (`"164653-2"`). Cast to string. Never use it as a key.
- Every run writes a `staff_sync_runs` record: created, updated, deactivated,
  reactivated, and errors.
- The sync must be idempotent — running it twice changes nothing the second time.

## Activation model — confirmed (HR/IT, Aug 2026)

The open questions on `personType` / `assignmentStatus` are resolved:

- **Everyone in the payload gets an account.** `person_type` and `assignment_status`
  are stored for display/filtering but **do not gate login**. Do not build activation
  logic on their values.
- **Activation is payload presence, nothing else.** Present in the payload →
  `is_active = true`. Departed staff linger in the payload for a week or more with
  `assignmentEndDate` set; they stay active during that window. Once a person **drops
  out of the payload** → `is_active = false`. Never hard-delete.
- **Reactivation by identity.** Match `external_id` first, then `kennitala_hash`. A
  returning seasonal ranger reactivates their existing disabled account (posts,
  comments, memberships intact) — kennitala is the reliable key because email can be
  missing. This is why the circuit breaker is critical: absence is the deactivation
  signal, so a partial API failure must never mass-disable the workforce.
- **Missing contact info.** `email` and `phone` may both be null. No-email staff can
  use neither the email invitation nor the Entra path — their onboarding is an open
  1D question; the schema allows null.

## Names

`employeeName` is a single string containing Icelandic patronymics
("Jóhanna Malen Skúladóttir"). Store it whole as `display_name`. Derive `given_name`
from the first token for greetings. **Never split into first/last** and never sort by
the last token — patronymics are not surnames.
