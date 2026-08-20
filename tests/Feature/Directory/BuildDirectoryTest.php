<?php

declare(strict_types=1);

use App\Actions\Directory\BuildDirectory;
use App\Models\User;

/**
 * @param  array{search?: string|null, department?: string|null}  $filters
 * @return array<int, string>
 */
function directoryNames(array $filters = []): array
{
    return app(BuildDirectory::class)($filters, 100)->pluck('display_name')->all();
}

test('the directory lists active staff and excludes inactive ones', function () {
    User::factory()->create(['display_name' => 'Virk Vera']);
    User::factory()->inactive()->create(['display_name' => 'Horfin Halla']);

    expect(directoryNames())->toContain('Virk Vera')->not->toContain('Horfin Halla');
});

test('search matches name, job title and department', function () {
    User::factory()->create(['display_name' => 'Anna Jóns', 'job_title' => 'Landvörður', 'organization_name' => 'NVS Norðurland']);
    User::factory()->create(['display_name' => 'Bjarni Þór', 'job_title' => 'Bókari', 'organization_name' => 'NVS Vestursvæði']);

    expect(directoryNames(['search' => 'Landvörður']))->toContain('Anna Jóns')->not->toContain('Bjarni Þór');
    expect(directoryNames(['search' => 'Vestur']))->toContain('Bjarni Þór')->not->toContain('Anna Jóns');
});

test('the department filter narrows to one organisation', function () {
    User::factory()->create(['display_name' => 'Nord Maður', 'organization_name' => 'NVS Norðurland']);
    User::factory()->create(['display_name' => 'Vest Maður', 'organization_name' => 'NVS Vestursvæði']);

    expect(directoryNames(['department' => 'NVS Norðurland']))
        ->toContain('Nord Maður')
        ->not->toContain('Vest Maður');
});

test('results are ordered by given name, not the patronymic', function () {
    User::factory()->create(['display_name' => 'Bjarni Þórsson']);
    User::factory()->create(['display_name' => 'Anna Jónsdóttir']);

    $names = directoryNames();

    expect(array_search('Anna Jónsdóttir', $names, true))
        ->toBeLessThan(array_search('Bjarni Þórsson', $names, true));
});
