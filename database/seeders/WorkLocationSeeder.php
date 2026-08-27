<?php

namespace Database\Seeders;

use App\Models\WorkLocation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkLocationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The picklist for the "Hvar er unnið" board: NVS offices and park sites, plus the
     * two non-place options (working from home, out in the field). Idempotent so it can
     * be re-run without duplicating rows.
     *
     * @var list<string>
     */
    private const LOCATIONS = [
        'Urriðaholt',
        'Akureyri',
        'Mývatn',
        'Þingvellir',
        'Snæfellsnes',
        'Vatnajökull',
        'Vestmannaeyjar',
        'Heima',
        'Vettvangur',
    ];

    public function run(): void
    {
        foreach (self::LOCATIONS as $order => $name) {
            WorkLocation::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true, 'sort_order' => $order],
            );
        }
    }
}
