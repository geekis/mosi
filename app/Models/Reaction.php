<?php

namespace App\Models;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $reactable_id
 * @property string $reactable_type
 * @property int $user_id
 * @property ReactionType $type
 */
#[Fillable(['reactable_id', 'reactable_type', 'user_id', 'type'])]
class Reaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => ReactionType::class];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
