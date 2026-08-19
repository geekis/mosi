<?php

declare(strict_types=1);

use App\Enums\AssignmentStatus;
use App\Enums\AuthMethod;
use App\Enums\PersonType;
use App\Models\User;

it('derives given_name from the first token of the display name', function () {
    $user = User::factory()->create(['display_name' => 'Jóhanna Malen Skúladóttir']);

    expect($user->given_name)->toBe('Jóhanna');
});

it('leaves a single-token name as its own given name', function () {
    $user = User::factory()->create(['display_name' => 'Björk']);

    expect($user->given_name)->toBe('Björk');
});

it('casts auth_method to the enum', function () {
    $user = User::factory()->entra()->create();

    expect($user->auth_method)->toBe(AuthMethod::Entra);
});

it('returns typed person_type and assignment_status for known values', function () {
    $user = User::factory()->create([
        'person_type' => PersonType::Employee->value,
        'assignment_status' => AssignmentStatus::Active->value,
    ]);

    expect($user->personType())->toBe(PersonType::Employee)
        ->and($user->assignmentStatus())->toBe(AssignmentStatus::Active);
});

it('returns null for an unconfirmed person_type rather than throwing', function () {
    $user = User::factory()->create(['person_type' => 'Verktaki']);

    expect($user->personType())->toBeNull()
        ->and($user->person_type)->toBe('Verktaki');
});

it('resolves the supervisor relationship', function () {
    $manager = User::factory()->manager()->create();
    $ranger = User::factory()->create(['supervisor_id' => $manager->id]);

    expect($ranger->supervisor->is($manager))->toBeTrue()
        ->and($manager->subordinates->pluck('id'))->toContain($ranger->id);
});

it('scopes to active staff only', function () {
    User::factory()->count(3)->create();
    User::factory()->inactive()->create();

    expect(User::query()->active()->count())->toBe(3);
});

describe('factory states', function () {
    it('entra() produces a work account with no password', function () {
        $user = User::factory()->entra()->create();

        expect($user->email)->toEndWith('@nattura.is')
            ->and($user->auth_method)->toBe(AuthMethod::Entra)
            ->and($user->password)->toBeNull();
    });

    it('passwordAuth() produces a personal-email invitation account', function () {
        $user = User::factory()->passwordAuth()->create();

        expect($user->email)->not->toEndWith('@nattura.is')
            ->and($user->auth_method)->toBe(AuthMethod::Password)
            ->and($user->password)->not->toBeNull();
    });

    it('withoutEmail() produces a staff member with no contact details', function () {
        $user = User::factory()->withoutEmail()->create();

        expect($user->email)->toBeNull()
            ->and($user->phone)->toBeNull();
    });

    it('generates a ten-digit kennitala', function () {
        $user = User::factory()->create();

        expect($user->kennitala)->toMatch('/^\d{10}$/');
    });
});
