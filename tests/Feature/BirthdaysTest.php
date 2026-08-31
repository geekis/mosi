<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Give a user a specific birthday (month/day only), leaving the rest of the factory
 * defaults intact. Written straight to the derived columns so the test controls the
 * date without crafting a kennitala.
 */
function userBornOn(int $month, int $day, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->forceFill(['birth_month' => $month, 'birth_day' => $day])->saveQuietly();

    return $user->refresh();
}

test('the birthday is derived from the kennitala, month and day only', function () {
    // 14 September, year 1990 — the year must never survive into the model.
    $user = User::factory()->create();
    $user->kennitala = '1409901239';

    expect($user->birth_day)->toBe(14)
        ->and($user->birth_month)->toBe(9);
});

test('birthdayFromKennitala rejects companies and malformed values', function () {
    // Person born 3 April; hyphen/spacing is stripped before parsing.
    expect(User::birthdayFromKennitala('0304901239'))->toBe(['birth_month' => 4, 'birth_day' => 3]);
    expect(User::birthdayFromKennitala('030490-1239'))->toBe(['birth_month' => 4, 'birth_day' => 3]);
    // Company kennitalas add 40 to the day → not a birthday.
    expect(User::birthdayFromKennitala('4304901239'))->toBeNull();
    // Impossible month or day.
    expect(User::birthdayFromKennitala('0399901239'))->toBeNull();
    expect(User::birthdayFromKennitala('0004901239'))->toBeNull();
    // Too short or missing.
    expect(User::birthdayFromKennitala('030'))->toBeNull();
    expect(User::birthdayFromKennitala(null))->toBeNull();
});

test('clearing the kennitala clears the derived birthday', function () {
    $user = User::factory()->create();
    $user->kennitala = '1409901239';
    expect($user->birth_month)->toBe(9);

    $user->kennitala = null;

    expect($user->birth_month)->toBeNull()
        ->and($user->birth_day)->toBeNull();
});

test('the feed rail lists upcoming birthdays soonest first, highlighting today', function () {
    $me = User::factory()->create();
    // The viewer has no birthday, so only the people below appear.
    $me->forceFill(['birth_month' => null, 'birth_day' => null])->saveQuietly();

    $today = CarbonImmutable::today();
    $todayPerson = userBornOn($today->month, $today->day, ['display_name' => 'Afmaelisbarn Dagsins']);
    $soonPerson = userBornOn($today->addDays(3)->month, $today->addDays(3)->day, ['display_name' => 'Braedur Fljotur']);
    $laterPerson = userBornOn($today->addDays(10)->month, $today->addDays(10)->day, ['display_name' => 'Seinni Karlinn']);

    Livewire::actingAs($me)
        ->test('pages::feed')
        ->assertSee(__('Upcoming birthdays'))
        ->assertSeeInOrder([
            __('Upcoming birthdays'),
            'Afmaelisbarn Dagsins',
            'Braedur Fljotur',
            'Seinni Karlinn',
        ])
        // Today's person is flagged, not shown as a date.
        ->assertSee('Afmæli í dag', escape: false);
});

test('the feed rail shows at most four upcoming birthdays', function () {
    $me = User::factory()->create();
    $me->forceFill(['birth_month' => null, 'birth_day' => null])->saveQuietly();

    $today = CarbonImmutable::today();
    for ($i = 1; $i <= 5; $i++) {
        userBornOn($today->addDays($i)->month, $today->addDays($i)->day);
    }

    // Asserted on the widget's own collection: every active user's name is also embedded
    // in the mention-autocomplete data, so a plain assertDontSee on a name would be noise.
    Livewire::actingAs($me)
        ->test('pages::feed')
        ->assertViewHas('upcomingBirthdays', fn ($birthdays) => $birthdays->count() === 4);
});

test('a birthday is never stored or formatted with a year', function () {
    $person = User::factory()->create();
    // Born 6 June 1985.
    $person->kennitala = '0606851239';

    // Nothing on the model carries the year.
    expect($person->getAttributes())->not->toHaveKey('birth_year')
        ->and($person->birth_month)->toBe(6)
        ->and($person->birth_day)->toBe(6);

    // The rail's date label ("6. júní") shows no year.
    $label = $person->nextBirthday()?->translatedFormat('j. F');
    expect($label)->not->toContain('1985')->not->toContain('85');
});
