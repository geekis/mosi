<?php

declare(strict_types=1);

namespace App\Actions\Reactions;

use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class Unreact
{
    public function __invoke(User $user, Model $reactable): void
    {
        Reaction::query()
            ->where('reactable_id', $reactable->getKey())
            ->where('reactable_type', $reactable->getMorphClass())
            ->where('user_id', $user->id)
            ->delete();
    }
}
