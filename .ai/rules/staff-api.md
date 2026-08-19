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
- Any birthday feature must be opt-in and must not expose the year.

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

## Names

`employeeName` is a single string containing Icelandic patronymics
("Jóhanna Malen Skúladóttir"). Store it whole as `display_name`. Derive `given_name`
from the first token for greetings. **Never split into first/last** and never sort by
the last token — patronymics are not surnames.
