<?php

declare(strict_types=1);

namespace App\Actions\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Build the "Latest" tab: every published post the user may see, newest first —
 * visibility-scoped, cursor-paginated, with the author, group, media and
 * reaction/comment counts eager-loaded so a page is a constant number of queries.
 */
final class BuildLatest
{
    /**
     * @return CursorPaginator<int, Post>
     */
    public function __invoke(User $user, int $perPage = 15): CursorPaginator
    {
        return Post::query()
            ->latestFor($user)
            ->with([
                'author.media', 'group', 'media', 'reactions',
                'comments' => fn ($query) => $query->with('author.media')->oldest(),
            ])
            ->withCount(['reactions', 'comments'])
            ->cursorPaginate($perPage);
    }
}
