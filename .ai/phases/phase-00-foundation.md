# Phase 0 — Foundation

**Branch:** `phase-0-foundation`
**Rules to read:** `.ai/rules/deployment.md`, `.ai/rules/localisation.md`,
`.ai/rules/testing.md`, `.ai/rules/frontend.md`

**Goal:** an empty app that boots, lints, tests, and has a house style. No features.

---

## Prompt

> We're starting Mosi from a scaffolded Laravel 13 project with no packages yet.
> This is phase 0: foundation only, no features.
>
> Read `CLAUDE.md` and the rule files listed above first. Give me a plan before you
> start, then work through it.
>
> **1. Packages.** Install and publish config for:
>
> ```
> laravel/socialite socialiteproviders/microsoft-azure
> spatie/laravel-permission spatie/laravel-medialibrary
> spatie/laravel-translatable spatie/laravel-activitylog spatie/laravel-sluggable
> laravel/scout laravel/horizon laravel/sanctum laravel/reverb
> filament/filament:"^5.0"
> livewire/flux livewire/flux-pro
> spatie/laravel-backup intervention/image propaganistas/laravel-phone
> ```
>
> Dev: `pestphp/pest larastan/larastan laravel/telescope barryvdh/laravel-debugbar`
>
> Do **not** install `laravel/volt` — it's merged into Livewire 4 core.
> Flux Pro needs `auth.json`; assume it already exists and never read it.
>
> **2. Config.**
> - Timezone `Atlantic/Reykjavik`, `Carbon::setLocale('is')` in a service provider
> - `APP_NAME=Mosi`; nothing hardcodes the product name
> - MySQL collation `utf8mb4_is_0900_ai_ci`
> - Redis for cache, sessions, queues, broadcasting
> - Reverb configured and running locally
> - `Model::preventLazyLoading()` and `preventSilentlyDiscardingAttributes()` outside
>   production
>
> **3. Localisation scaffolding.**
> - `lang/is/` and `lang/en/` with `app.php`, `auth.php`, `validation.php`
> - Locale middleware resolving: user preference → `Accept-Language` → `is`
> - An Icelandic pluralisation helper handling the 1/21/31/101 singular rule, with
>   unit tests
>
> **4. Structure.** Create `app/Actions/` and `app/Enums/` with a README in each
> explaining the convention from `CLAUDE.md`.
>
> **5. Design tokens.** Tailwind 4 theme with NVS colours — ask me for the palette
> before choosing any. Set up Flux with the Inter font and a dark mode variant.
>
> **6. Tooling.** Pint, Larastan level 6, Pest 4 with browser testing configured. A
> GitHub Actions workflow running all three on PRs — the workflow file only, don't
> configure anything remote.
>
> **7. `.gitignore`** must include `auth.json` and `.claude/settings.local.json`.
>
> Commit locally in logical chunks as you go. Do not push.

---

## Acceptance criteria

- [ ] `composer install && npm install && npm run build` succeeds from clean
- [ ] `php artisan test --compact` passes (pluralisation helper tests exist)
- [ ] `vendor/bin/pint --dirty --format agent` clean
- [ ] Larastan level 6 clean
- [ ] Reverb starts locally; Horizon dashboard reachable
- [ ] `php artisan config:show app.name` returns `Mosi`
- [ ] Switching locale to `en` changes UI strings
- [ ] `app/Actions/` and `app/Enums/` exist with README files
- [ ] `auth.json` is gitignored and was never read

## Notes

Ask for the NVS colour palette rather than inventing one — this is a public-sector
agency with existing brand guidelines.

Flag anything needing Forge configuration (Horizon daemon, Reverb daemon + Nginx
WebSocket proxy, Scheduler, Meilisearch) as a written list for a human. Do not attempt
it.
