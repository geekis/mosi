# Actions

Business logic for Mosi lives here — **not** in Livewire components, Filament
resources, console commands, or (in v2) API controllers. Those are all thin callers.

## The rule

> Every write operation is a single-purpose invokable class in `app/Actions/`.
> — `CLAUDE.md`, rule 1

One Action does one thing. It is the single place a given change to the system can
happen, so the Livewire web UI, the Filament admin, the `app:sync-staff` command, and
the future Teams and native apps all funnel through the same code. This is what keeps
v2 tractable.

## Shape

- Invokable: a single `__invoke()` (or a descriptively named single public method such
  as `handle()` when the caller reads better that way — pick one convention per
  subtree and stick to it).
- Grouped by domain: `app/Actions/Posts/CreatePost.php`,
  `app/Actions/Groups/JoinGroup.php`, `app/Actions/Staff/SyncStaffFromApi.php`.
- Dependencies via constructor injection (resolved from the container).
- Explicit return types and typed parameters.
- Authorisation is enforced (policy check) either in the Action or in every caller —
  prefer the Action so no surface can skip it.

```php
namespace App\Actions\Posts;

final class CreatePost
{
    public function __invoke(User $author, CreatePostData $data): Post
    {
        // validate invariants, write, dispatch events — the one place a post is created
    }
}
```

Create with: `php artisan make:class Actions/Posts/CreatePost`

## Testing

**Every Action class has a test. No exceptions** (`.ai/rules/testing.md`). Actions are
plain classes, so they are cheap to test directly with factories — do that rather than
driving them through a Livewire component.
