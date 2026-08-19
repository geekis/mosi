# Mosi rule index

Read every rule file whose globs cover the paths you are about to touch. Also run
`grep -rin '<keyword>' .ai/rules` — a path match alone misses cross-cutting traps.

| Rule file | Globs |
|---|---|
| `staff-api.md` | `app/Actions/Staff/**`, `app/Models/User.php`, `app/Console/Commands/SyncStaff*`, `database/migrations/*users*`, `database/migrations/*staff*` |
| `auth.md` | `app/Actions/Auth/**`, `app/Http/Middleware/**`, `app/Policies/**`, `routes/auth.php`, `resources/views/pages/auth/**` |
| `frontend.md` | `resources/views/**`, `resources/css/**`, `resources/js/**` |
| `localisation.md` | `lang/**`, `resources/views/**`, `app/Notifications/**`, `app/Mail/**`, `app/Jobs/**` |
| `database.md` | `database/**`, `app/Models/**` |
| `admin.md` | `app/Filament/**` |
| `realtime.md` | `app/Events/**`, `app/Actions/Chat/**`, `config/reverb.php`, `config/broadcasting.php` |
| `testing.md` | `tests/**` |
| `deployment.md` | `.env.example`, `config/**`, `composer.json`, `package.json` — and read it before suggesting any infrastructure change |

Architecture-wide rules (Action classes, thin components, authorisation, git safety)
live in the root `CLAUDE.md`, not here.

Record new durable rules with the `record-rule` Boost tool rather than editing these
files by hand, so the glob mapping stays consistent.
