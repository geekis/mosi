<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

/**
 * Comment authorisation delegates to the post's policy: you can see or moderate a
 * comment exactly when you can see or moderate its post — so a comment on a secret
 * group's post never leaks to a non-member.
 */
class CommentPolicy
{
    public function view(User $user, Comment $comment): bool
    {
        $post = Post::query()->find($comment->post_id);

        return $post !== null && $user->can('view', $post);
    }

    public function update(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return true;
        }

        $post = Post::query()->find($comment->post_id);

        return $post !== null && $user->can('delete', $post);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }
}
