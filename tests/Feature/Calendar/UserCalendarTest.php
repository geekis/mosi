<?php

declare(strict_types=1);

use App\Actions\Microsoft\FetchUserCalendar;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('services.azure.tenant', 'test-tenant');
    config()->set('services.azure.client_id', 'client-id');
    config()->set('services.azure.client_secret', 'secret');
    config()->set('services.azure.graph.enabled', true);
    config()->set('services.azure.graph.base_url', 'https://graph.microsoft.com/v1.0');
    Cache::flush();
});

function fakeCalendar(array $events): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['value' => $events]),
    ]);
}

function standupEvent(string $day = '2026-08-27'): array
{
    return [
        'subject' => 'Standup',
        'isAllDay' => false,
        'showAs' => 'busy',
        'start' => ['dateTime' => $day.'T09:00:00.0000000', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => $day.'T09:15:00.0000000', 'timeZone' => 'UTC'],
        'location' => ['displayName' => 'Room 3'],
        'organizer' => ['emailAddress' => ['name' => 'Anna Jónsdóttir', 'address' => 'anna@x']],
        'attendees' => [
            ['emailAddress' => ['name' => 'Anna Jónsdóttir']],
            ['emailAddress' => ['name' => 'Björn Egilsson']],
        ],
    ];
}

test('it maps calendarView events and caches the window', function () {
    fakeCalendar([standupEvent()]);
    $user = User::factory()->create(['entra_oid' => 'oid-1']);
    $from = CarbonImmutable::parse('2026-08-27');
    $to = $from->addDay();

    $events = app(FetchUserCalendar::class)($user, $from, $to);

    expect($events)->toHaveCount(1);
    expect($events[0]['subject'])->toBe('Standup')
        ->and($events[0]['location'])->toBe('Room 3')
        ->and($events[0]['organizer'])->toBe('Anna Jónsdóttir')
        ->and($events[0]['attendees'])->toBe(['Anna Jónsdóttir', 'Björn Egilsson'])
        ->and($events[0]['is_all_day'])->toBeFalse()
        ->and($events[0]['start']->format('H:i'))->toBe('09:00');

    // Second call is served from cache: only the token + one calendarView request happened.
    app(FetchUserCalendar::class)($user, $from, $to);
    Http::assertSentCount(2);
});

test('it caches serialisation-safe strings and rehydrates Carbon on read', function () {
    fakeCalendar([standupEvent()]);
    $user = User::factory()->create(['entra_oid' => 'oid-1']);
    $from = CarbonImmutable::parse('2026-08-27');
    $to = $from->addDay();

    $events = app(FetchUserCalendar::class)($user, $from, $to);

    // Callers receive Carbon instances…
    expect($events[0]['start'])->toBeInstanceOf(CarbonImmutable::class);

    // …but the cache stores plain ISO strings, so Redis never has to unserialize a
    // Carbon object (which fails with "incomplete object").
    $cacheKey = sprintf('calendar:%d:%s:%s', $user->id, $from->toDateString(), $to->toDateString());
    $cached = Cache::get($cacheKey);
    expect($cached[0]['start'])->toBeString()
        ->and($cached[0]['end'])->toBeString();
});

test('it returns nothing and calls no endpoint for a user without a mailbox', function () {
    $user = User::factory()->create(['entra_oid' => null]);
    Http::fake();

    expect(app(FetchUserCalendar::class)($user, now(), now()->addDay()))->toBe([]);
    Http::assertNothingSent();
});

test('it returns nothing when Graph is disabled', function () {
    config()->set('services.azure.graph.enabled', false);
    $user = User::factory()->create(['entra_oid' => 'oid-1']);
    Http::fake();

    expect(app(FetchUserCalendar::class)($user, now(), now()->addDay()))->toBe([]);
    Http::assertNothingSent();
});

test('the calendar island shows a colleague events with full detail', function () {
    fakeCalendar([standupEvent(now()->toDateString())]);
    $viewer = User::factory()->create();
    $target = User::factory()->create(['entra_oid' => 'oid-1']);

    Livewire::actingAs($viewer)->test('pages::people.calendar', ['user' => $target])
        ->assertSee('Standup')
        ->assertSee('Room 3');
});

test('a Graph error yields an empty calendar instead of throwing', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403),
    ]);
    $user = User::factory()->create(['entra_oid' => 'oid-1']);

    expect(app(FetchUserCalendar::class)($user, now(), now()->addDay()))->toBe([]);
});

test('the calendar island survives a Graph 403 and shows the empty state', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403),
    ]);
    $viewer = User::factory()->create();
    $target = User::factory()->create(['entra_oid' => 'oid-1']);

    Livewire::actingAs($viewer)->test('pages::people.calendar', ['user' => $target])
        ->assertOk()
        ->assertSee(__('No events this week.'));
});

test('the calendar island shows an empty state for a user without a mailbox', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['entra_oid' => null]);
    Http::fake();

    Livewire::actingAs($viewer)->test('pages::people.calendar', ['user' => $target])
        ->assertSee(__('No calendar available for this person.'));

    Http::assertNothingSent();
});

test('navigating weeks shifts the window', function () {
    fakeCalendar([]);
    $viewer = User::factory()->create();
    $target = User::factory()->create(['entra_oid' => 'oid-1']);

    $component = Livewire::actingAs($viewer)->test('pages::people.calendar', ['user' => $target]);
    $start = $component->get('weekStart');

    $component->call('previousWeek');

    expect(CarbonImmutable::parse($component->get('weekStart')))
        ->toEqual(CarbonImmutable::parse($start)->subWeek());
});
