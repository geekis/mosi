<?php

declare(strict_types=1);

use App\Actions\Auth\EntraTokenValidator;
use App\Enums\AuthMethod;
use App\Models\User;
use RuntimeException;

test('an already authenticated user is sent straight into the app', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('teams.auth'))
        ->assertRedirect(route('dashboard'));
});

test('a guest gets the silent sign-in bootstrap page', function () {
    $this->get(route('teams.auth'))
        ->assertOk()
        ->assertSee('id="teams-auth"', false)
        ->assertSee('teams-auth', false); // the bundled Teams SDK entry
});

test('a valid Teams token establishes a session and links the account', function () {
    $user = User::factory()->create([
        'email' => 'egill@eigind.is',
        'auth_method' => AuthMethod::Password->value,
    ]);

    $this->mock(EntraTokenValidator::class)
        ->shouldReceive('validate')
        ->once()
        ->andReturn(['oid' => 'oid-teams', 'email' => 'egill@eigind.is']);

    $this->postJson(route('teams.auth.store'), ['token' => 'a-teams-jwt'])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertAuthenticatedAs($user->fresh());

    expect($user->fresh()->entra_oid)->toBe('oid-teams')
        ->and($user->fresh()->auth_method)->toBe(AuthMethod::Both);
});

test('an invalid Teams token is rejected without a session', function () {
    $this->mock(EntraTokenValidator::class)
        ->shouldReceive('validate')
        ->once()
        ->andThrow(new RuntimeException('bad signature'));

    $this->postJson(route('teams.auth.store'), ['token' => 'tampered'])
        ->assertStatus(401);

    $this->assertGuest();
});

test('a valid token with no matching staff record is rejected', function () {
    $this->mock(EntraTokenValidator::class)
        ->shouldReceive('validate')
        ->once()
        ->andReturn(['oid' => 'oid-stranger', 'email' => 'stranger@eigind.is']);

    $this->postJson(route('teams.auth.store'), ['token' => 'valid-but-unknown'])
        ->assertStatus(403);

    $this->assertGuest();
});

test('a missing token is rejected before any validation', function () {
    $this->mock(EntraTokenValidator::class)->shouldNotReceive('validate');

    $this->postJson(route('teams.auth.store'), [])
        ->assertStatus(422);

    $this->assertGuest();
});
