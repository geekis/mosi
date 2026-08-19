# Mosi — build phases

One phase = one branch = one PR = one or more Claude Code sessions. A phase is done
when its acceptance criteria pass, Pint and Larastan are clean, and the work is
committed locally. **Never pushed by an agent** — see `CLAUDE.md`.

Rules referenced by each phase live in `.ai/rules/`. Read the matching ones before
starting.

## V1 — the intranet

| # | Phase | Goal | Blocked by |
|---|---|---|---|
| 0 | Foundation | Packages, tooling, locale scaffolding, design tokens, Reverb running locally. Empty app that boots, lints, tests. | — |
| 1 | Identity & staff sync | Staff API sync, dual auth (Entra + password), invitations, roles. **Largest phase.** | HR answers (below) |
| 2 | Groups & membership | Groups with visibility, auto-enrolment from org data, follow system. | 1 |
| 3 | Posts, reactions, comments | Post model, media, reactions, comments, mentions. | 2 |
| 4 | Feed | The personalised feed query, cursor-paginated, visibility-scoped. | 3 |
| 5 | Application shell | Three-column layout, nav, megamenu, right rail, profile card, quick links. | 4 |
| 6 | Profiles & directory | Profile pages, avatar/banner upload, staff directory with filters. | 5 |
| 7 | Events | Events, RSVP, ICS export, upcoming-events widget. | 5 |
| 8 | Notifications | Channel-agnostic notifications, bell UI, digest emails, preferences. | 4 |
| 9 | Chat | Conversations, DMs, group chat, presence, read receipts. | 8 |
| 10 | Search | Meilisearch across posts, people, groups, events, documents. | 4, 7 |
| 11 | Content & documents | Official news, static pages, document library. | 3 |
| 12 | Admin panel | Filament resources, analytics, activity log, sync trigger. | 11 |
| 13 | Hardening & launch | Performance, security, accessibility, backups, GDPR. | all |

## V2 — Teams & native app

| # | Phase | Goal |
|---|---|---|
| 14 | Public API | Sanctum, versioned `/api/v1`, Resources, OpenAPI spec — thin controllers over the Actions from phase 1 onward. |
| 15 | Teams app | Manifest, personal + channel tabs, SSO token exchange, deep links, notification bot. |
| 16 | Native app | Built on phase 14. |

## Open questions blocking phase 1

Get answers before starting. Everything in the sync's activation logic depends on
them:

1. **HR:** what values can `personType` take besides `"Starfsmaður"`? Do contractors
   or board members appear, and should they get accounts?
2. **HR:** what values can `assignmentStatus` take besides `"Virkt starf"`? Which mean
   "may log in"?
3. **IT:** does the API return staff whose assignments have ended, or do they vanish
   from the payload? This decides whether deactivation reads a status field or infers
   from absence — and makes the circuit breaker critical in the second case.
4. **HR:** do any staff have no email address at all? If so, how do they get invited?

## How to run a phase

1. Start Claude Code from the project root. Confirm with `/memory` that `CLAUDE.md`
   loaded.
2. `git checkout -b phase-N-name`
3. Paste the phase brief. Ask for a plan first on the larger phases.
4. Review, iterate, let it commit locally.
5. **You** push and open the PR.

Ask for tests first on anything touching authorisation or the staff sync.
