<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Authorisation for posts. `view` mirrors {@see Post::scopeVisibleTo()} — a post in a
 * secret group is invisible to non-members. Pinning and moderating a group's posts is
 * limited to the group's owners/moderators and comms (spec §7).
 */
class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        if ($post->user_id === $user->id) {
            return true;
        }

        if (! $post->isPublished()) {
            return false;
        }

        if ($post->group_id === null) {
            return true; // a published personal post
        }

        return Group::query()->whereKey($post->group_id)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return true; // group membership is enforced by the CreatePost action
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id || $this->moderates($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id || $this->moderates($user, $post);
    }

    public function pin(User $user, Post $post): bool
    {
        return $this->moderates($user, $post);
    }

    private function moderates(User $user, Post $post): bool
    {
        if ($user->hasAnyRoleName('super-admin', 'comms')) {
            return true;
        }

        if ($post->group_id === null) {
            return false;
        }

        return DB::table('group_user')
            ->where('group_id', $post->group_id)
            ->where('user_id', $user->id)
            ->whereIn('role', [GroupRole::Owner->value, GroupRole::Moderator->value])
            ->exists();
    }
}
