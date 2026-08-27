# Microsoft Graph setup — presence & calendars

Mosi reads live Teams **presence** (the status dot on avatars) and **calendars** (the
weekly agenda on a person's profile) app-only from Microsoft Graph, using the existing
Entra app registration via the client-credentials flow. Everything is gated behind the
`MS_GRAPH_ENABLED` flag and stays dark until the steps below are done — while off,
presence falls back to the in-app `last_active_at` heartbeat and the calendar panel shows
an empty state.

Reference: [RBAC for Applications in Exchange Online](https://learn.microsoft.com/en-us/exchange/permissions-exo/application-rbac)
· [getPresencesByUserId](https://learn.microsoft.com/en-us/graph/api/cloudcommunications-getpresencesbyuserid?view=graph-rest-1.0)
· [calendarView](https://learn.microsoft.com/en-us/graph/api/user-list-calendarview?view=graph-rest-1.0)

## Tenant values

| | Test | Production |
|---|---|---|
| Domain | `eigind.is` (site `mosi.geek.is`) | separate tenant (future, `mosi.nattura.is`) |
| Client (app) id | `4ff55392-8af0-410d-b9e3-5793c9aee080` | _TBD_ |
| Tenant id | `ef992f08-2662-4e92-85a3-6a1ee9ca5c4e` | _TBD_ |

The client id/secret/tenant are the **same** app registration already used for SSO; no new
app is needed. Graph reads use its client secret via client-credentials.

---

## 1. Entra — application permissions

App registration → **API permissions** → *Add a permission* → Microsoft Graph →
**Application permissions**:

- **`Presence.Read.All`** — required. Presence is not mailbox data and cannot be scoped
  with Exchange RBAC, so it is granted org-wide here.
- **`Calendars.Read`** — only if you want the **unscoped** setup (app can read *every*
  mailbox in the tenant). **Skip this** if you plan to scope calendars in step 2.

Then **Grant admin consent** for the tenant.

> ⚠️ RBAC scoping and Entra consent are a **union**. If `Calendars.Read` is consented
> org-wide in Entra *and* scoped in Exchange RBAC, the union cancels the scoping. To
> actually limit calendars, consent **only** `Presence.Read.All` in Entra and grant
> calendar access solely through the RBAC assignment below.

---

## 2. (Recommended) Scope calendar reads to staff mailboxes — Exchange RBAC for Applications

Without scoping, `Calendars.Read` reads every mailbox in the tenant. RBAC for Applications
(the supported replacement for the deprecated Application Access Policy) limits the app to
a chosen set of mailboxes.

**Prerequisites:** the **Exchange Administrator** role; a mail-enabled security group /
M365 group containing the staff whose calendars should be visible (e.g. "Mosi Staff"); and
the app's **Enterprise Application Object ID** — Entra admin center → *Enterprise
applications* → search the client id → **Object ID** (⚠️ *not* the App registrations
page's object id).

```powershell
# 1. Connect as an Exchange admin
Install-Module ExchangeOnlineManagement -Scope CurrentUser
Connect-ExchangeOnline -UserPrincipalName you@eigind.is

# 2. Point Exchange at the app's Entra service principal
#    -AppId = client id ; -ObjectId = the Enterprise App Object ID from Entra
New-ServicePrincipal -AppId 4ff55392-8af0-410d-b9e3-5793c9aee080 `
  -ObjectId 84921f18-89ab-4faf-8c69-41024ecfa5ff -DisplayName "Mosi Graph"

# 3. Create a management scope covering the staff mailboxes.
#    Option A — by a mail-enabled security group (only DIRECT members are in scope):
Get-Group -Identity "Eigind ehf" | Select-Object -ExpandProperty DistinguishedName   # copy the DN
New-ManagementScope -Name "Eigind Staff Mailboxes" `
  -RecipientRestrictionFilter "MemberOfGroup -eq 'ad62d332-f4ae-4a82-a0bd-eecbf49280c0'"

#    Option B — by a custom attribute / department instead:
# New-ManagementScope -Name "Mosi Staff Mailboxes" -RecipientRestrictionFilter "CustomAttribute1 -eq 'Mosi'"

# 4. Assign SCOPED Calendars.Read to the app
New-ManagementRoleAssignment -App 4ff55392-8af0-410d-b9e3-5793c9aee080 `
  -Role "Application Calendars.Read" -CustomResourceScope "Mosi Staff Mailboxes"

# 5. Verify — InScope = True for a staff mailbox, False for an out-of-scope one
Test-ServicePrincipalAuthorization -Identity 4ff55392-8af0-410d-b9e3-5793c9aee080 `
  -Resource ranger@eigind.is | Format-Table
```

Notes:
- `-App` accepts the AppId, the service-principal ObjectId, or the DisplayName.
- Grant changes take **30 min – 2 hours** to take effect (the app's auth cache).
  `Test-ServicePrincipalAuthorization` bypasses the cache, so use it to confirm at once.
- **Nested groups don't count** — only direct members are in scope. Supported group types:
  M365 Groups, Mail-Enabled Security Groups, Distribution Lists.

---

## 3. Enable the feature (Forge)

Add to `.env` (via the Forge UI) and to `.env.example`:

```dotenv
MS_GRAPH_ENABLED=true
# Optional override; defaults to the v1.0 endpoint:
# MS_GRAPH_BASE_URL=https://graph.microsoft.com/v1.0
```

The presence poll (`app:sync-presence`) is already registered on the scheduler and runs
every minute; it no-ops while `MS_GRAPH_ENABLED` is off, so shipping it before consent is
safe. Reverb must be running for live dot updates (it already is).

---

## 4. Verify end to end

```bash
php artisan app:sync-presence   # populates the presence cache and broadcasts changes
```

- Set your own Teams status to Busy → within a minute your dot goes red across the
  directory / profile / chat without a page reload.
- Open a colleague's profile → their weekly calendar agenda renders with real events.
- A password-only user (no `entra_oid`) keeps the heartbeat-based green/grey dot and shows
  a "no calendar" panel.

---

## How the code uses this

- `App\Actions\Microsoft\AcquireGraphToken` — client-credentials token, cached in Redis
  until just before expiry.
- `App\Support\Microsoft\GraphClient` — injects the token, honours 429 `Retry-After`, and
  short-circuits to `null` when `MS_GRAPH_ENABLED` is off.
- `App\Actions\Microsoft\FetchPresences` + `App\Console\Commands\SyncPresenceCommand` +
  `App\Events\UserPresenceChanged` — poll → diff → broadcast on the `presence` channel.
- `App\Actions\Presence\ResolvePresence` — the single source both the server-rendered dot
  and the Alpine store read (Graph value, else heartbeat fallback).
- `App\Actions\Microsoft\FetchUserCalendar` + `resources/views/pages/people/⚡calendar.blade.php`
  — weekly `calendarView` agenda on the profile.
