<?php

declare(strict_types=1);

use App\Actions\Whereabouts\BuildWhereaboutsBoard;
use App\Actions\Whereabouts\SetWhereabouts;
use App\Enums\AbsenceType;
use App\Models\User;
use App\Models\Whereabouts;
use App\Models\WorkLocation;
use Illuminate\Support\Carbon;

test('setting a location registers the day', function () {
    $user = User::factory()->create();
    $location = WorkLocation::factory()->create(['name' => 'Urriðaholt']);

    $entry = app(SetWhereabouts::class)($user, now(), location: $location);

    expect($entry)->not->toBeNull()
        ->and($entry->work_location_id)->toBe($location->id)
        ->and($entry->absence)->toBeNull()
        ->and($entry->statusLabel())->toBe('Urriðaholt');
});

test('setting an absence registers the day with no location', function () {
    $user = User::factory()->create();

    $entry = app(SetWhereabouts::class)($user, now(), absence: AbsenceType::Veikindi);

    expect($entry->work_location_id)->toBeNull()
        ->and($entry->absence)->toBe(AbsenceType::Veikindi)
        ->and($entry->isAbsence())->toBeTrue();
});

test('a day is at most one registration, upserted in place', function () {
    $user = User::factory()->create();
    $location = WorkLocation::factory()->create();

    app(SetWhereabouts::class)($user, now(), location: $location);
    app(SetWhereabouts::class)($user, now(), absence: AbsenceType::Orlof);

    expect(Whereabouts::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Whereabouts::query()->where('user_id', $user->id)->first()->absence)
        ->toBe(AbsenceType::Orlof);
});

test('clearing a day deletes the registration', function () {
    $user = User::factory()->create();
    $location = WorkLocation::factory()->create();
    app(SetWhereabouts::class)($user, now(), location: $location);

    $result = app(SetWhereabouts::class)($user, now());

    expect($result)->toBeNull()
        ->and(Whereabouts::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('a day cannot be both a location and an absence', function () {
    $user = User::factory()->create();
    $location = WorkLocation::factory()->create();

    app(SetWhereabouts::class)($user, now(), location: $location, absence: AbsenceType::Orlof);
})->throws(DomainException::class);

test('an absence hides its reason from others but not from the person', function () {
    $entry = Whereabouts::factory()->absent(AbsenceType::Veikindi)->create();

    // Icelandic default locale: generic label vs the real reason.
    expect($entry->statusLabel(revealReason: false))->toBe('Fjarverandi')
        ->and($entry->statusLabel(revealReason: true))->toBe('Veikindi');
});

test('only the owner may edit a registration', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $entry = Whereabouts::factory()->for($owner)->create();

    expect($owner->can('update', $entry))->toBeTrue()
        ->and($other->can('update', $entry))->toBeFalse();
});

test('the board can be filtered by location and division, and can exclude a person', function () {
    $me = User::factory()->create(['organization_name' => 'NVS Vestursvæði']);
    $mate = User::factory()->create(['organization_name' => 'NVS Vestursvæði']);
    $other = User::factory()->create(['organization_name' => 'NVS Norðurland']);
    $here = WorkLocation::factory()->create();
    $there = WorkLocation::factory()->create();

    app(SetWhereabouts::class)($me, now(), location: $here);
    app(SetWhereabouts::class)($mate, now(), location: $here);
    app(SetWhereabouts::class)($other, now(), location: $there);

    $board = app(BuildWhereaboutsBoard::class)(Carbon::now(), [
        'location_id' => $here->id,
        'division' => 'NVS Vestursvæði',
        'exclude_user_id' => $me->id,
    ]);

    // Only $mate matches: same location, same division, and not the excluded $me.
    expect($board->pluck('user.id')->all())->toBe([$mate->id]);
});

test('the board lists active colleagues registered for the day, sorted by given name', function () {
    $location = WorkLocation::factory()->create();
    $bjork = User::factory()->create(['display_name' => 'Björk Guðmunds']);
    $anna = User::factory()->create(['display_name' => 'Anna Þórs']);
    $inactive = User::factory()->inactive()->create();

    foreach ([$bjork, $anna, $inactive] as $person) {
        app(SetWhereabouts::class)($person, now(), location: $location);
    }

    $board = app(BuildWhereaboutsBoard::class)(Carbon::now());

    expect($board)->toHaveCount(2)
        ->and($board->first()->user->id)->toBe($anna->id) // Anna sorts before Björk
        ->and($board->pluck('user.id'))->not->toContain($inactive->id);
});
