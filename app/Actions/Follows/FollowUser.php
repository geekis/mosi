<?php

declare(strict_types=1);

namespace App\Actions\Follows;

use App\Models\User;
use App\Notifications\NewFollower;
use DomainException;

final class FollowUser
{
    public function __invoke(User $follower, User $followed): void
    {
        if ($follower->is($followed)) {
            throw new DomainException('A user cannot follow themselves.');
        }

        $alreadyFollowing = $follower->following()->whereKey($followed->id)->exists();

        $follower->following()->syncWithoutDetaching([$followed->id]);

        // Notify once, only on a new follow — re-following is idempotent and silent.
        if (! $alreadyFollowing) {
            $followed->notify(new NewFollower($follower));
        }
    }
}
