<?php

namespace App\Models;

use App\Enums\AbsenceType;
use Database\Factories\WhereaboutsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's registration of where they are on a single day: either a {@see WorkLocation}
 * (present) or an {@see AbsenceType} (away), never both, never neither. Upserted per day by
 * the SetWhereabouts action.
 *
 * The specific absence reason (Orlof vs Veikindi) is private: {@see self::statusLabel()}
 * shows colleagues only "Fjarverandi", so recording sick leave never discloses it.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property int|null $work_location_id
 * @property AbsenceType|null $absence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Whereabouts extends Model
{
    /** @use HasFactory<WhereaboutsFactory> */
    use HasFactory;

    protected $table = 'whereabouts';

    /** @var list<string> */
    protected $fillable = ['user_id', 'date', 'work_location_id', 'absence'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'absence' => AbsenceType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<WorkLocation, $this>
     */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * Whether the person is away (as opposed to at a work location) this day.
     */
    public function isAbsence(): bool
    {
        return $this->absence !== null;
    }

    /**
     * The label to show for this registration. A work location is shown by name to
     * everyone; an absence is shown as the generic "Fjarverandi" — unless $revealReason is
     * true (the viewer is the person themselves), which surfaces Orlof vs Veikindi.
     */
    public function statusLabel(bool $revealReason = false): string
    {
        if (! $this->isAbsence()) {
            return (string) $this->workLocation?->name;
        }

        return $revealReason ? $this->absence->label() : __('Away');
    }
}
