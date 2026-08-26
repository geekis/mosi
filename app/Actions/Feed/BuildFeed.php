<?php

declare(strict_types=1);

namespace App\Actions\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Build a user's personalised feed: cursor-paginated, visibility-scoped, with the
 * author, group, media and reaction/comment counts eager-loaded so rendering a page is
 * a constant number of queries (.ai/rules/database.md).
 */
final class BuildFeed
{
    /**
     * @return CursorPaginator<int, Post>
     */
    public function __invoke(User $user, int $perPage = 15): CursorPaginator
    {
        return Post::query()
            ->feedFor($user)
            ->with([
                'author.media', 'group', 'media', 'reactions',
                'comments' => fn ($query) => $query->with('author.media')->oldest(),
            ])
            ->withCount(['reactions', 'comments'])
            ->cursorPaginate($perPage);
    }
}
