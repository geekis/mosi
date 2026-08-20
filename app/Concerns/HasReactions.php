<?php

namespace App\Concerns;

use App\Models\Reaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Emoji reactions for any model (posts, comments).
 */
trait HasReactions
{
    /**
     * @return MorphMany<Reaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /**
     * A count of reactions per type, e.g. ['like' => 4, 'love' => 1].
     *
     * @return array<string, int>
     */
    public function reactionCounts(): array
    {
        return $this->reactions()
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->all();
    }
}
