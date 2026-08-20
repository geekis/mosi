<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('prints the payload structure without leaking any values', function () {
    config([
        'services.staff_api.url' => 'https://staff.test/api/employees',
        'services.staff_api.username' => 'svc',
        'services.staff_api.password' => 'secret',
    ]);

    $rows = json_decode((string) file_get_contents(base_path('tests/Fixtures/staff/payload.json')), true)['rows'];

    Http::fake([
        'staff.test/*' => Http::response(['items' => $rows, 'hasMore' => false]),
    ]);

    $exitCode = Artisan::call('app:inspect-staff-api');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Envelope keys: items, hasMore')
        ->and($output)->toContain('nationalIdentifier: string')
        ->and($output)->toContain('assignments: array[2]')
        // the actual kennitala and name must never appear
        ->and($output)->not->toContain('0101907789')
        ->and($output)->not->toContain('Jóhanna');
});
