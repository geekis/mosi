<?php

namespace App\Models;

use Database\Factories\WorkLocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A place staff can register as working from on the "Hvar er unnið" board — Urriðaholt,
 * Akureyri, Heima, and so on. A fixed picklist managed by content managers and admins in
 * the Filament panel; offered to every authenticated user.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkLocation extends Model
{
    /** @use HasFactory<WorkLocationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Whereabouts, $this>
     */
    public function whereabouts(): HasMany
    {
        return $this->hasMany(Whereabouts::class);
    }

    /**
     * Only locations that are switched on, in display order — the picklist for staff.
     *
     * @param  Builder<WorkLocation>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
