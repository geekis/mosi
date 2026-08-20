<?php

declare(strict_types=1);

namespace App\Actions\Follows;

use App\Models\User;
use DomainException;

final class FollowUser
{
    public function __invoke(User $follower, User $followed): void
    {
        if ($follower->is($followed)) {
            throw new DomainException('A user cannot follow themselves.');
        }

        $follower->following()->syncWithoutDetaching([$followed->id]);
    }
}
