# Localisation

**Globs:** `lang/**`, `resources/views/**`, `app/Notifications/**`, `app/Mail/**`,
`app/Jobs/**`

Icelandic (`is`) is the default. English (`en`) is the fallback. Some staff are
English-speaking; both languages are first-class.

## Three layers, three mechanisms

**1. UI strings** → `lang/is/*.php`, `lang/en/*.php`. Never hardcode display text,
not even in an admin panel or an error message.

**2. Admin-authored content** → `spatie/laravel-translatable` with JSON columns.
Covers group names and descriptions, quick links, nav items, static pages, event
titles and descriptions, document titles. **Add translatable casts when the model is
created — never retrofit**, because retrofitting means migrating every one of these
tables.

**3. User-generated content** (posts, comments, chat) → stored as written, never
translated. An optional on-demand "Translate" button hitting Azure Translator is a
later nicety, not a v1 feature.

## Locale resolution

Order: user's saved preference → `nationality` hint from the staff sync (`IS` → `is`,
else `en`) → `Accept-Language` → `is`.

The user's choice always wins and is editable in their profile.

## The queued-job trap

Notifications, mailables, and queued jobs must render in the **recipient's** locale,
not the sender's and not the queue worker's. Always wrap:

```php
App::setLocale($notifiable->locale);
```

Digest emails going to a mixed-language audience must be rendered per recipient. This
is the most common localisation bug in this codebase's shape — check it every time.

## Icelandic specifics

- Timezone `Atlantic/Reykjavik`; `Carbon::setLocale('is')` in a service provider.
- **Icelandic uses the singular for 1, 21, 31, 101** ("21 færsla", not "21 færslur").
  Laravel's `trans_choice` does not know this. Use the project pluralisation helper.
- **Sort people by `given_name`.** Patronymics are not surnames.
- MySQL collation `utf8mb4_is_0900_ai_ci` so þ/ð/æ/ö sort correctly.
- Meilisearch needs Icelandic stop words and synonyms configured, and handles
  Icelandic inflection far better than MySQL fulltext.
- Filament ships partial Icelandic translations. Fill the gaps in
  `lang/vendor/filament/is/`.
- Date formats: Icelandic uses `d.m.Y`, English `j M Y`. Never hardcode either.

## Product name

"Mosi" is never translated. In Icelandic copy it declines normally (Mosi, Mosa, Mosa,
Mosa) — check with the Icelandic copy owner whether UI strings use the bare form
("Mosi") or the definite form ("Mosinn"), and stay consistent across all strings.
