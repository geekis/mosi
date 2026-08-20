<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Actions\Mentions\NotifyMentions;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use DomainException;

/**
 * Add a comment, or a one-level reply. Visibility (can the author see the post) is
 * enforced by the caller via the policy; this action guards the structural rules and
 * notifies anyone @mentioned who can see the content.
 */
final class AddComment
{
    public function __construct(
        private readonly NotifyMentions $notifyMentions,
    ) {}

    public function __invoke(User $author, Post $post, string $body, ?Comment $parent = null): Comment
    {
        if ($parent !== null) {
            if ($parent->post_id !== $post->id) {
                throw new DomainException('The parent comment belongs to a different post.');
            }

            if ($parent->parent_id !== null) {
                throw new DomainException('Replies are only one level deep.');
            }
        }

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'parent_id' => $parent?->id,
            'body' => $body,
        ]);

        ($this->notifyMentions)($comment, $author, $comment->body);

        return $comment;
    }
}
