<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Audit record for one `app:sync-staff` run. `total_rows` (the payload size the API
 * reported) is what the circuit breaker compares run-to-run.
 *
 * @property int $id
 * @property string $status running|success|failed|aborted
 * @property int|null $total_rows
 * @property int $created_count
 * @property int $updated_count
 * @property int $deactivated_count
 * @property int $reactivated_count
 * @property array<string, mixed>|null $errors
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable([
    'status', 'total_rows', 'created_count', 'updated_count', 'deactivated_count',
    'reactivated_count', 'errors', 'started_at', 'finished_at',
])]
class StaffSyncRun extends Model
{
    public const string STATUS_RUNNING = 'running';

    public const string STATUS_SUCCESS = 'success';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_ABORTED = 'aborted';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'deactivated_count' => 'integer',
            'reactivated_count' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * The most recent run that completed successfully — the baseline for the circuit
     * breaker. Aborted and failed runs are ignored so a bad run cannot poison the next.
     *
     * @param  Builder<StaffSyncRun>  $query
     */
    public function scopeLastSuccessful(Builder $query): void
    {
        $query->where('status', self::STATUS_SUCCESS)->latest('finished_at');
    }
}
