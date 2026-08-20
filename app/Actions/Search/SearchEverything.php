<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Models\Event;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Cross-entity search over people, groups, posts and events — every result set scoped to
 * what the searcher may see, so a secret group (and its posts and events) never appears
 * to a non-member (.ai/rules/auth.md). This is a database implementation behind a stable
 * interface; a Meilisearch/Scout backend can replace the internals without changing
 * callers (.ai/rules/deployment.md).
 *
 * @phpstan-type Results array{people: Collection<int, User>, groups: Collection<int, Group>, posts: Collection<int, Post>, events: Collection<int, Event>}
 */
final class SearchEverything
{
    /**
     * @return Results
     */
    public function __invoke(User $user, string $term, int $perType = 6): array
    {
        $term = trim($term);

        if ($term === '') {
            return ['people' => collect(), 'groups' => collect(), 'posts' => collect(), 'events' => collect()];
        }

        $like = '%'.$term.'%';

        $people = User::query()
            ->active()
            ->with('media')
            ->where(fn ($q) => $q->where('display_name', 'like', $like)
                ->orWhere('job_title', 'like', $like)
                ->orWhere('organization_name', 'like', $like))
            ->orderBy('given_name')
            ->limit($perType)
            ->get();

        $groups = Group::query()
            ->visibleTo($user)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like))
            ->limit($perType)
            ->get();

        $posts = Post::query()
            ->visibleTo($user)
            ->published()
            ->with(['author', 'group'])
            ->where('body', 'like', $like)
            ->latest('published_at')
            ->limit($perType)
            ->get();

        $events = Event::query()
            ->visibleTo($user)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('location', 'like', $like))
            ->orderBy('starts_at')
            ->limit($perType)
            ->get();

        return compact('people', 'groups', 'posts', 'events');
    }
}
