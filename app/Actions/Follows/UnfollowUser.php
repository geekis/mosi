<?php

declare(strict_types=1);

namespace App\Actions\Follows;

use App\Models\User;

final class UnfollowUser
{
    public function __invoke(User $follower, User $followed): void
    {
        $follower->following()->detach($followed->id);
    }
}
