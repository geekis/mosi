<?php

declare(strict_types=1);

use App\Actions\Staff\SyncStaffFromApi;
use App\Enums\AuthMethod;
use App\Models\StaffSyncRun;
use App\Models\User;
use App\Notifications\StaffSyncCircuitBreakerTripped;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/** @return array<string, mixed> */
function staffPayload(): array
{
    return json_decode((string) file_get_contents(base_path('tests/Fixtures/staff/payload.json')), true);
}

/**
 * Fake the staff API to return one page containing the given rows, reporting
 * $totalRows (which defaults to the row count but can be overridden for the breaker).
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
function fakeStaffApi(array $rows, ?int $totalRows = null): void
{
    config([
        'services.staff_api.url' => 'https://staff.test/api/employees?row_offset=0&fetch_size=1000',
        'services.staff_api.username' => 'svc',
        'services.staff_api.password' => 'secret',
    ]);

    Http::fake([
        'staff.test/*' => Http::response([
            'totalRows' => $totalRows ?? count($rows),
            'rows' => $rows,
        ]),
    ]);
}

function runSync(): StaffSyncRun
{
    return app(SyncStaffFromApi::class)();
}

function superAdmin(): User
{
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->entra()->create();
    $user->load('roles');
    $user->assignRole('super-admin');

    return $user;
}

it('creates staff from the payload with fields mapped and normalised', function () {
    fakeStaffApi(staffPayload()['rows']);

    $run = runSync();

    expect(User::count())->toBe(4)
        ->and($run->status)->toBe(StaffSyncRun::STATUS_SUCCESS)
        ->and($run->created_count)->toBe(4)
        ->and($run->total_rows)->toBe(4);

    $johanna = User::where('external_id', '100001')->sole();

    expect($johanna->display_name)->toBe('Jóhanna Malen Skúladóttir')
        ->and($johanna->given_name)->toBe('Jóhanna')
        ->and($johanna->email)->toBe('johanna.skuladottir@nattura.is')
        ->and($johanna->employee_number)->toBe('4521')
        ->and($johanna->is_active)->toBeTrue()
        ->and($johanna->is_manager)->toBeFalse()
        ->and($johanna->person_type)->toBe('Starfsmaður')
        ->and($johanna->locale)->toBe('is')
        ->and($johanna->kennitala)->toBe('0101907789');
});

it('derives locale from the nationality hint', function () {
    fakeStaffApi(staffPayload()['rows']);

    runSync();

    expect(User::where('external_id', '100004')->value('locale'))->toBe('en')
        ->and(User::where('external_id', '100001')->value('locale'))->toBe('is');
});

it('is idempotent — a second run changes nothing', function () {
    fakeStaffApi(staffPayload()['rows']);

    runSync();
    $second = runSync();

    expect(User::count())->toBe(4)
        ->and($second->created_count)->toBe(0)
        ->and($second->updated_count)->toBe(0)
        ->and($second->reactivated_count)->toBe(0)
        ->and($second->deactivated_count)->toBe(0);
});

it('normalises the 4712-12-31 Oracle sentinel to null', function () {
    fakeStaffApi(staffPayload()['rows']);

    runSync();

    expect(User::where('external_id', '100001')->value('assignment_ends_at'))->toBeNull()
        // A real end date is kept.
        ->and(User::where('external_id', '100003')->value('assignment_ends_at')?->toDateString())->toBe('2024-09-30');
});

it('accepts assignmentNumber as either an integer or a string', function () {
    // 100001 has an integer assignmentNumber, 100003 a string one.
    fakeStaffApi(staffPayload()['rows']);

    $run = runSync();

    expect($run->status)->toBe(StaffSyncRun::STATUS_SUCCESS)
        ->and(User::count())->toBe(4);
});

it('normalises the integer gsm_phone to an international string', function () {
    fakeStaffApi(staffPayload()['rows']);

    runSync();

    expect(User::where('external_id', '100001')->value('phone'))->toBe('+3546612345');
});

it('uses only the primaryFlag Y assignment', function () {
    fakeStaffApi(staffPayload()['rows']);

    runSync();

    // 100001's primary is Landvörður at NVS Norðurland (100%); the non-primary
    // Verkefnastjóri at Skrifstofa forstjóra (20%) must be ignored.
    $johanna = User::where('external_id', '100001')->sole();

    expect($johanna->job_title)->toBe('Landvörður')
        ->and($johanna->organization_id)->toBe('39164')
        ->and($johanna->assignment_percent)->toBe(100);
});

it('derives auth_method from the email domain', function () {
    fakeStaffApi(staffPayload()['rows']);

    runSync();

    expect(User::where('external_id', '100001')->value('auth_method'))->toBe(AuthMethod::Entra)
        ->and(User::where('external_id', '100003')->value('auth_method'))->toBe(AuthMethod::Password);
});

it('resolves supervisorPersonId to a users.id in a second pass', function () {
    fakeStaffApi(staffPayload()['rows']);

    runSync();

    $johanna = User::where('external_id', '100001')->sole();
    $sigridur = User::where('external_id', '100002')->sole();

    expect($johanna->supervisor_id)->toBe($sigridur->id)
        ->and($sigridur->supervisor_id)->toBeNull();
});

it('deactivates staff who fall out of the payload without deleting them', function () {
    // A single stateful stub whose payload we mutate between runs (calling Http::fake
    // twice would just stack stubs and the first would keep winning).
    config([
        'services.staff_api.url' => 'https://staff.test/api/employees',
        'services.staff_api.token' => 'test-token',
    ]);
    $payload = ['rows' => staffPayload()['rows']];
    Http::fake([
        'staff.test/*' => function () use (&$payload) {
            return Http::response([
                'totalRows' => count($payload['rows']),
                'rows' => $payload['rows'],
            ]);
        },
    ]);

    runSync();

    // Isolate deactivation from the circuit breaker: dropping 1 of only 4 fixture rows
    // is a 25% fall (fine at 260 staff, but it would trip the breaker here), so clear the
    // baseline first.
    StaffSyncRun::query()->delete();

    // Next run: 100003 (the seasonal ranger) is gone from the payload.
    $payload['rows'] = array_values(array_filter(
        staffPayload()['rows'],
        fn (array $row) => $row['personId'] !== 100003,
    ));
    $run = runSync();

    $ranger = User::where('external_id', '100003')->sole();

    expect($ranger->is_active)->toBeFalse()
        ->and($run->deactivated_count)->toBe(1)
        ->and(User::count())->toBe(4); // retained, not deleted
});

it('reactivates a returning ranger by kennitala without duplicating', function () {
    // A departed ranger, disabled, whose personId will differ when they return but whose
    // kennitala is the same as payload person 100003.
    $returning = User::factory()->inactive()->create([
        'external_id' => 'legacy-777',
        'kennitala' => '2508912229',
    ]);

    fakeStaffApi(staffPayload()['rows']);
    $run = runSync();

    $returning->refresh();

    expect(User::whereNotNull('kennitala_hash')
        ->where('kennitala_hash', User::hashKennitala('2508912229'))->count())->toBe(1)
        ->and($returning->is_active)->toBeTrue()
        ->and($returning->external_id)->toBe('100003') // same record, personId updated
        ->and($run->reactivated_count)->toBe(1)
        ->and($run->created_count)->toBe(3); // the other three are new
});

it('aborts via the circuit breaker when the payload shrinks more than 15%', function () {
    Notification::fake();
    $admins = collect([superAdmin(), superAdmin()]);

    // A healthy previous run of 100 rows.
    StaffSyncRun::create([
        'status' => StaffSyncRun::STATUS_SUCCESS,
        'total_rows' => 100,
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour(),
    ]);

    $usersBefore = User::count();
    fakeStaffApi(staffPayload()['rows'], totalRows: 80); // 80 < 85

    $run = runSync();

    expect($run->status)->toBe(StaffSyncRun::STATUS_ABORTED)
        ->and($run->created_count)->toBe(0)
        ->and(User::count())->toBe($usersBefore); // nothing created or changed

    Notification::assertSentTo($admins->all(), StaffSyncCircuitBreakerTripped::class);
});

it('does not abort when the drop is within 15%', function () {
    StaffSyncRun::create([
        'status' => StaffSyncRun::STATUS_SUCCESS,
        'total_rows' => 4,
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour(),
    ]);

    fakeStaffApi(staffPayload()['rows'], totalRows: 4);

    expect(runSync()->status)->toBe(StaffSyncRun::STATUS_SUCCESS);
});

it('paginates by row_offset and honours the ORDS items/hasMore envelope', function () {
    config([
        'services.staff_api.url' => 'https://staff.test/api/employees',
        'services.staff_api.username' => 'svc',
        'services.staff_api.password' => 'secret',
        'services.staff_api.fetch_size' => 2,
    ]);

    $rows = staffPayload()['rows'];

    Http::fake([
        'staff.test/*' => function ($request) use ($rows) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) ($query['row_offset'] ?? 0);

            return Http::response([
                'items' => array_slice($rows, $offset, 2),
                'hasMore' => ($offset + 2) < count($rows),
            ]);
        },
    ]);

    $run = runSync();

    expect($run->created_count)->toBe(4)
        ->and(User::count())->toBe(4);
});

it('sends basic auth and the row_offset pagination param', function () {
    fakeStaffApi(staffPayload()['rows']);

    runSync();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization')
        && str_starts_with((string) $request->header('Authorization')[0], 'Basic ')
        && str_contains($request->url(), 'row_offset=0'));
});

it('populates users through the app:sync-staff command', function () {
    fakeStaffApi(staffPayload()['rows']);

    $this->artisan('app:sync-staff')->assertSuccessful();

    expect(User::count())->toBe(4)
        ->and(StaffSyncRun::where('status', StaffSyncRun::STATUS_SUCCESS)->exists())->toBeTrue();
});
