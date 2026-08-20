# Mosi — product specification

What Mosi does, screen by screen. `.ai/rules/` covers *how* to build; this covers
*what*. Phase briefs in `.ai/phases/` are written from this document.

Open questions are marked **[OPEN]** — do not resolve them by guessing.

---

## 1. Home — the feed

The landing page after login. Three columns on desktop, stacked on mobile.

### Layout

```
┌──────────────────────────────────────────────────────────┐
│  Top nav: logo · megamenu · search · notifications · me  │
├────────────┬──────────────────────┬──────────────────────┤
│ Left nav   │  Feed                │  Right rail          │
│            │                      │                      │
│ Profile    │  [For you | Latest]  │  Upcoming events     │
│ card       │                      │  Birthdays           │
│            │  Composer            │  New starters        │
│ My groups  │                      │  Most-read news      │
│ Chat       │  Post                │  Active poll         │
│ Quick      │  Post                │  Weather             │
│ links      │  Post…               │                      │
└────────────┴──────────────────────┴──────────────────────┘
```

Mobile: profile card collapses to an avatar in the top bar; left nav becomes a
drawer; right rail moves below the feed. Chat gets a persistent bottom-bar icon with
an unread badge — 25% of staff have no Teams, so chat must be one tap away.

### Feed tabs

**For you** (default) — ranked. Posts from groups the user belongs to, plus posts from
people they follow. Ranking inputs, in rough priority:

1. Pinned posts from the org-wide group (always top, always visible)
2. Official posts (`is_official`, from the comms group)
3. Recency, decaying over ~72 hours
4. Engagement (reactions + comments), weighted below recency
5. Posts from groups the user interacts with most

**Latest** — pure reverse chronological, same visibility scope, no ranking. This tab
is the escape hatch when ranking hides something; it must always be one click away.

Both cursor-paginated with infinite scroll. Tab choice persists per user.

**[OPEN]** Should "For you" ever surface posts from groups the user is *not* in
(discovery)? Default assumption: no — Mosi is small enough that group membership is
the right boundary.

### Composer

Inline at the top of the feed. Group selector (defaults to the org-wide group), rich
text via `flux:editor`, image and file attachments, @mentions, link previews. Draft
autosave. Scheduling and pinning for `comms` and `group-owner` only.

---

## 2. Profile card (left column)

Persistent on the feed and on profile pages.

- Banner image (user-uploaded, cropped)
- Profile image (user-uploaded, cropped; initials fallback — **no Gravatar**, it
  leaks email hashes to a third party)
- Display name
- Job title (`jobName` from the staff API)
- Organisation (`organizationName`)
- Followers count · Following count — both clickable
- Link to full profile

Neither image comes from the staff API; both are user-uploaded. **[OPEN]** Should we
pull the Entra profile photo via Graph as a default for the 75% who have one? It would
give most staff a real photo on day one.

---

## 3. Left navigation

- Profile card (above)
- **My groups** — every group the user belongs to, with unread indicators. Org-wide
  group pinned first. Long lists collapse behind "Show all".
- **Chat** — icon with unread count, opens chat
- **Quick links** — admin-managed, ordered by drag-and-drop in Filament, optionally
  scoped by role or organisation
- Directory, Events, Documents, News

---

## 4. Top navigation

- Logo (links home)
- **Megamenu** — data-driven from a `nav_items` table, never hardcoded. Supports
  nested dropdowns and a wide multi-column panel. Items are translatable, orderable,
  optionally role-scoped, and may link internally or externally.
- Global search (`flux:command`, keyboard shortcut)
- Notification bell
- User menu: profile, settings, language switcher, log out

---

## 5. Right rail widgets

Each widget is a lazy island (`.ai/rules/frontend.md`) behind a skeleton, so the feed
paints first. Widgets are slot-based — order and visibility configurable in admin.

### 5.1 Upcoming events
Next 3–5 events the user can see. Title, date, location, RSVP state. Links to the
events page. ICS download per event.

### 5.2 Birthdays
**Opt-in, default off.** Date of birth is derivable from kennitala, but deriving it
does not make it ours to publish.

- Users opt in from profile settings; a clear explanation of what becomes visible
- Shows **day and month only, never the year**
- Today's birthdays, plus "this week"
- **[OPEN]** Should there be an org-wide notification, or only the rail widget?
  Recommendation: rail only. A notification for every one of 260 staff becomes noise.

### 5.3 New starters this month
Derived from `assignment_starts_at`. Name, job title, organisation, avatar. Links to
profile.

- Rolling 30 days rather than calendar month, so someone starting on the 28th isn't
  invisible by the 1st
- Excludes rehired seasonal staff returning under an existing `personId` — a ranger
  back for their fourth summer is not a new starter
- Respects the same opt-out as birthdays? **[OPEN]** — arguably a starter
  announcement is legitimate org communication rather than personal data sharing.
  Confirm with HR.

### 5.4 Most-read news
Top 3–5 official posts by view count over a rolling 7 days. Requires per-post view
tracking (unique per user per day, not raw hits). Excludes anything in a `closed` or
`secret` group.

### 5.5 Active poll
Shows the current org-wide poll, if any.

Polls are a **post type**, not a separate entity — they live in the feed, get comments
and reactions, and the rail widget simply surfaces the active org-wide one. Single or
multiple choice, optional close date, results visible after voting or after close
(author's choice). Anonymous by default; the author sees counts, never who voted.

### 5.6 Weather
For 3–20 NVS locations — park stations, visitor centres, field offices.

- User picks which locations they care about; defaults to their own
  `organizationName` location
- Current conditions plus a short forecast; wind and warnings matter more than
  temperature for rangers
- Cached server-side and shared across users — never one API call per page load
- **[OPEN]** Source: Veðurstofa Íslands is the obvious choice for an Icelandic
  government agency. Confirm licensing and rate limits before building.
- **[OPEN]** The exact location list, with coordinates. Needed before phase 7.

---

## 6. Groups

- Types: `department` (auto-enrolled from `organizationId`), `interest`, `project`
- Visibility: `open` (anyone may see and join), `closed` (visible, join by request),
  `secret` (invisible to non-members)
- The org-wide group "Allir starfsmenn" is a real group with auto-enrolment, not a
  special case
- Group page: cover image, description, member list, posts, group events, files
- Roles: owner, moderator, member. `managerFlag === 'Y'` auto-grants owner on the
  user's own organisation group
- Name and description translatable

---

## 7. Posts, reactions, comments

- Rich text, images, files, link previews
- Reactions: 5–6 emoji, not a single like
- Comments with one level of replies; @mentions notify
- Pinning (group owners and comms), scheduling, drafts
- `is_official` flag for comms posts — styled distinctly, ranked higher
- Edit history visible; deletes are soft

---

## 8. Chat

Built in-house because ~25% of staff have no Teams account.

- DMs and group conversations
- Presence, typing indicators, read receipts, unread counts
- Attachments
- **[OPEN]** Does every group get an automatic chat thread, or are chat and groups
  fully separate? Recommendation: separate. Auto-threads for 40 groups create 40 dead
  channels.
- Messages soft-deleted, never hard-deleted (public-sector record-keeping)

---

## 9. Events

- Optionally scoped to a group; otherwise org-wide
- RSVP: going / maybe / no, with attendee list
- ICS export per event and a subscribable feed — this is what makes Mosi useful
  alongside Outlook
- Recurring events **[OPEN]** — needed for v1, or later?
- Title, description, location translatable

---

## 10. Directory

- All active staff, **sorted by given name**
- Filter by organisation, job title, location
- Search by name, job title, phone
- Card and list views
- Shows: photo, name, job title, organisation, email, phone, supervisor
- **Never shows kennitala**
- Org chart view from `supervisorPersonId` — **[OPEN]** v1 or later?

---

## 11. Search

Meilisearch across posts, people, groups, events, documents, pages. Scoped so that
`secret` group content never appears for non-members. Icelandic stop words and
synonyms configured. `flux:command` palette with a keyboard shortcut.

---

## 12. News, pages, documents

- **Official news** — posts flagged `is_official`, with a dedicated news page
- **Static pages** — policies, handbook, HR info. Simple page tree, translatable,
  editable in Filament
- **Document library** — MediaLibrary-backed, folders, versioning, download tracking,
  permission-scoped

---

## 13. Notifications

Channel-agnostic from the start: database, mail, broadcast, later web and native push.

- Bell UI with unread count
- Triggers: mentions, comments on your post, reactions, group invitations, event
  reminders, official announcements, chat messages
- Per-type preferences, per channel
- Daily or weekly digest email, rendered in the recipient's locale

---

## 14. Admin (Filament)

Users (view, invite, resend, deactivate — **never create**), groups, events, quick
links, nav items, pages, documents, polls, roles, weather locations, right-rail widget
order, and the staff sync log with manual trigger.

Analytics: active users, top posts, group engagement, most-read news.

---

## Open questions summary

| # | Question | Blocks | Owner |
|---|---|---|---|
| 1 | `personType` and `assignmentStatus` enums | Phase 1 | HR |
| 2 | Do departed staff vanish from the API payload? | Phase 1 | IT |
| 3 | Do any staff have no email at all? | Phase 1 | HR |
| 4 | Entra photo via Graph as avatar default? | Phase 6 | You |
| 5 | New starters — opt-out like birthdays? | Phase 5 | HR |
| 6 | Weather source, licensing, rate limits | Phase 7 | You |
| 7 | The 3–20 weather locations with coordinates | Phase 7 | You |
| 8 | Auto chat thread per group? | Phase 9 | You |
| 9 | Recurring events in v1? | Phase 7 | You |
| 10 | Org chart view in v1? | Phase 6 | You |
| 11 | "For you" — surface posts from non-member groups? | Phase 4 | You |
| 12 | Birthday notification, or rail widget only? | Phase 5 | You |
