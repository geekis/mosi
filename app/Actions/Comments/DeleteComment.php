<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Models\Comment;

/**
 * Soft-delete a comment (never hard-deleted — public-sector record-keeping).
 */
final class DeleteComment
{
    public function __invoke(Comment $comment): void
    {
        $comment->delete();
    }
}
