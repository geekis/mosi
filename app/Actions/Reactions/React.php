<?php

declare(strict_types=1);

namespace App\Actions\Reactions;

use App\Enums\ReactionType;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Add or change a user's reaction to an item. One reaction per user per item.
 */
final class React
{
    public function __invoke(User $user, Model $reactable, ReactionType $type): Reaction
    {
        return Reaction::updateOrCreate(
            [
                'reactable_id' => $reactable->getKey(),
                'reactable_type' => $reactable->getMorphClass(),
                'user_id' => $user->id,
            ],
            ['type' => $type->value],
        );
    }
}
