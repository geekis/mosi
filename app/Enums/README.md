# Enums

Backed enums for Mosi's fixed domain vocabularies live here. `app/Enums/` is a
pre-approved base folder (see `CLAUDE.md` → Overrides).

## When to use one

Whenever a column or value has a small, closed set of known states — prefer a backed
enum over loose strings or integer constants. Examples coming in later phases:

- `AuthMethod` — `entra`, `password`, `both`
- `PersonType` / `AssignmentStatus` — the NVS staff-API vocabularies (phase 1)
- Group visibility — `open`, `closed`, `secret`

## Conventions

- **TitleCase keys**, string-backed values that match what the database / external API
  stores: `case Password = 'password';`.
- Cast on the model (`protected function casts(): array`) so the enum is used
  end-to-end, never a raw string.
- Put behaviour on the enum (labels, colours, `->isActive()`) rather than scattering
  `match` statements across the app. Labels shown to staff are **translated** — return
  a translation key, never a hardcoded Icelandic or English string
  (`.ai/rules/localisation.md`).

```php
namespace App\Enums;

enum AuthMethod: string
{
    case Entra = 'entra';
    case Password = 'password';
    case Both = 'both';

    public function label(): string
    {
        return __("enums.auth_method.{$this->value}");
    }
}
```

Create with: `php artisan make:enum Enums/AuthMethod` (or `make:class`).
