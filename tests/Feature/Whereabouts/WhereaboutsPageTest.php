<?php

declare(strict_types=1);

use App\Actions\Whereabouts\SetWhereabouts;
use App\Enums\AbsenceType;
use App\Models\User;
use App\Models\WorkLocation;
use Livewire\Livewire;

test('the page registers my whereabouts for a day in the visible week', function () {
    $me = User::factory()->create();
    $location = WorkLocation::factory()->create();

    Livewire::actingAs($me)
        ->test('pages::whereabouts', ['date' => now()->toDateString()])
        ->call('setLocation', now()->toDateString(), $location->id);

    expect($me->whereabouts()->whereDate('date', now())->first()?->work_location_id)
        ->toBe($location->id);
});

test('the page marks and clears an absence for a day in the visible week', function () {
    $me = User::factory()->create();
    $day = now()->toDateString();

    $component = Livewire::actingAs($me)
        ->test('pages::whereabouts', ['date' => $day]);

    $component->call('setAbsence', $day, 'veikindi');
    expect($me->whereabouts()->whereDate('date', $day)->first()?->absence)
        ->toBe(AbsenceType::Veikindi);

    $component->call('clearDay', $day);
    expect($me->whereabouts()->whereDate('date', $day)->exists())->toBeFalse();
});

test('the absence buttons render a valid wire:click (literal quotes, not @js)', function () {
    $me = User::factory()->create();
    $day = now()->toDateString();

    // Regression: @js() inside wire:click breaks Livewire parsing, so the absence
    // buttons did nothing. They must render plain-quoted method calls.
    Livewire::actingAs($me)
        ->test('pages::whereabouts', ['date' => $day])
        ->assertSeeHtml('wire:click="setAbsence(\''.$day.'\', \'orlof\')"')
        ->assertSeeHtml('wire:click="setAbsence(\''.$day.'\', \'veikindi\')"')
        ->assertDontSeeHtml('@js(');
});

test('a day outside the visible week cannot be edited from the page', function () {
    $me = User::factory()->create();
    $location = WorkLocation::factory()->create();

    Livewire::actingAs($me)
        ->test('pages::whereabouts', ['date' => now()->toDateString()])
        ->call('setLocation', now()->addDays(30)->toDateString(), $location->id);

    expect($me->whereabouts()->exists())->toBeFalse();
});

test('the other-staff board lists a colleague but never myself', function () {
    $me = User::factory()->create(['display_name' => 'Ég Sjálf']);
    $mate = User::factory()->create(['display_name' => 'Anna Þórs']);
    $location = WorkLocation::factory()->create(['name' => 'Akureyri']);
    app(SetWhereabouts::class)($mate, now(), location: $location);
    app(SetWhereabouts::class)($me, now(), location: $location);

    Livewire::actingAs($me)
        ->test('pages::whereabouts-others', ['date' => now()->toDateString()])
        ->assertSee('Anna Þórs')   // a colleague appears
        ->assertDontSee('Ég Sjálf'); // I never appear on the "other staff" board
});

test('the other-staff board filters by work location', function () {
    $me = User::factory()->create();
    $akureyri = WorkLocation::factory()->create(['name' => 'Akureyri']);
    $urridaholt = WorkLocation::factory()->create(['name' => 'Urriðaholt']);
    $up = User::factory()->create(['display_name' => 'Norðan Maður']);
    $south = User::factory()->create(['display_name' => 'Sunnan Maður']);
    app(SetWhereabouts::class)($up, now(), location: $akureyri);
    app(SetWhereabouts::class)($south, now(), location: $urridaholt);

    Livewire::actingAs($me)
        ->test('pages::whereabouts-others', ['date' => now()->toDateString()])
        ->set('location', (string) $akureyri->id)
        ->assertSee('Norðan Maður')
        ->assertDontSee('Sunnan Maður');
});

test('the other-staff board filters by division', function () {
    $me = User::factory()->create();
    $location = WorkLocation::factory()->create();
    $west = User::factory()->create(['display_name' => 'Vestan Kona', 'organization_name' => 'NVS Vestursvæði']);
    $north = User::factory()->create(['display_name' => 'Norðan Kona', 'organization_name' => 'NVS Norðurland']);
    app(SetWhereabouts::class)($west, now(), location: $location);
    app(SetWhereabouts::class)($north, now(), location: $location);

    Livewire::actingAs($me)
        ->test('pages::whereabouts-others', ['date' => now()->toDateString()])
        ->set('division', 'NVS Vestursvæði')
        ->assertSee('Vestan Kona')
        ->assertDontSee('Norðan Kona');
});

test('a profile shows today\'s whereabouts, revealing the reason only to the person', function () {
    $person = User::factory()->create(['external_id' => 'person-1']);
    app(SetWhereabouts::class)($person, now(), absence: AbsenceType::Orlof);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::people.show', ['user' => $person])
        ->assertSee('Fjarverandi')
        ->assertDontSee('Orlof');

    Livewire::actingAs($person)
        ->test('pages::people.show', ['user' => $person])
        ->assertSee('Orlof');
});

test('the directory shows a person\'s location for today', function () {
    $location = WorkLocation::factory()->create(['name' => 'Akureyri']);
    $person = User::factory()->create();
    app(SetWhereabouts::class)($person, now(), location: $location);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::directory')
        ->assertSee('Akureyri');
});
