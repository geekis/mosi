<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Actions\Mentions\NotifyMentions;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentOnYourPost;
use App\Support\HtmlContent;
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

        $clean = HtmlContent::sanitize($body);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'parent_id' => $parent?->id,
            'body' => $clean,
        ]);

        ($this->notifyMentions)($comment, $author, $clean);

        // Tell the post's author, unless they commented on their own post or were the
        // one @mentioned (NotifyMentions already covered that case for the body author).
        $post->loadMissing('author');
        if ($post->author !== null && $post->author->isNot($author)) {
            $post->author->notify(new CommentOnYourPost($comment, $author));
        }

        return $comment;
    }
}
