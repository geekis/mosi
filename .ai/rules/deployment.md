# Deployment — Laravel Forge

**Globs:** `.env.example`, `config/**`, `composer.json`, `package.json` — and read
this before suggesting any infrastructure change.

Mosi runs on **Laravel Forge**. Boost's mention of Laravel Cloud does not apply.

## You never deploy

**Never run `git push`, a deploy script, a Forge CLI command, or a Forge API call.**

Forge Quick Deploy means a push to the production branch triggers a live deployment to
the NVS staff intranet. A push by you is a deploy by you. Commit locally, then stop
and say the work is ready.

You may *describe* an infrastructure change that a human should make in the Forge UI.
You may not make it.

## Server components

Mosi needs more than a web process. When adding a feature that depends on one of
these, say so explicitly so it can be configured in Forge:

| Component | Forge setup |
|---|---|
| Queue workers | Horizon daemon (not Forge's built-in queue worker — Horizon supersedes it) |
| WebSockets | Reverb daemon + Nginx WebSocket proxy on the site |
| Scheduler | Forge Scheduler running `schedule:run` every minute — required for `app:sync-staff` |
| Redis | Cache, sessions, queues, broadcasting |
| Meilisearch | Separate service; needs Icelandic stop words configured |
| MySQL 8 | Collation `utf8mb4_is_0900_ai_ci` |

Horizon and Reverb daemons must be restarted on deploy. That belongs in the deploy
script, which a human maintains.

## Environment

`.env` is managed in the Forge UI, not in the repo. Never read or modify `.env`.

When a feature needs a new environment variable, add it to `.env.example` with a safe
placeholder and say clearly that it must also be set in Forge — a variable that exists
only in `.env.example` will be missing in production.

## Backups & compliance

- `spatie/laravel-backup` to off-server storage (S3 or Azure Blob).
- Mosi holds staff personal data including kennitala. Backups are as sensitive as the
  database: encrypted, access-controlled, with a documented retention period.
- GDPR: staff data export and a retention policy for departed employees. Deactivated
  users are retained, not deleted — but retention is not indefinite, and that policy
  is a decision for NVS, not for code.

## Before launch

Query optimisation pass, Horizon in production, security headers, rate limiting,
WCAG 2.2 AA audit in both languages, and a restore test — an untested backup is not a
backup.
