<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $mentionable_id
 * @property string $mentionable_type
 * @property int $user_id
 */
#[Fillable(['mentionable_id', 'mentionable_type', 'user_id'])]
class Mention extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function mentionable(): MorphTo
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
