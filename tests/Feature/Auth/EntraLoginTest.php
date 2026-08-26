<?php

declare(strict_types=1);

use App\Actions\Auth\ResolveEntraLogin;
use App\Enums\AuthMethod;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

/*
 * The resolution Action — the security-critical core. SSO never creates users, matches
 * the immutable oid first, and links (never duplicates) an existing account.
 */

test('a first entra login links an existing password account and upgrades it to both', function () {
    $user = User::factory()->create([
        'email' => 'linkme@nattura.is',
        'auth_method' => AuthMethod::Password->value,
    ]);

    $resolved = app(ResolveEntraLogin::class)('oid-abc', 'LinkMe@nattura.is');

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($user))->toBeTrue()
        ->and($resolved->entra_oid)->toBe('oid-abc')
        ->and($resolved->auth_method)->toBe(AuthMethod::Both)
        ->and(User::count())->toBe(1);
});

test('a first entra login binds the oid to a work account without changing its method', function () {
    $user = User::factory()->entra()->create();

    $resolved = app(ResolveEntraLogin::class)('oid-work', $user->email);

    expect($resolved->is($user))->toBeTrue()
        ->and($resolved->entra_oid)->toBe('oid-work')
        ->and($resolved->auth_method)->toBe(AuthMethod::Entra);
});

test('a returning login matches on the oid even when the email has changed', function () {
    $user = User::factory()->entra()->create();
    $user->entra_oid = 'oid-stable';
    $user->save();

    $resolved = app(ResolveEntraLogin::class)('oid-stable', 'new-address@nattura.is');

    expect($resolved->is($user))->toBeTrue();
});

test('a deactivated account is rejected after the callback and is not linked', function () {
    $user = User::factory()->inactive()->create([
        'email' => 'gone@nattura.is',
        'auth_method' => AuthMethod::Password->value,
    ]);

    $resolved = app(ResolveEntraLogin::class)('oid-gone', 'gone@nattura.is');

    expect($resolved)->toBeNull()
        ->and($user->fresh()->entra_oid)->toBeNull()
        ->and($user->fresh()->auth_method)->toBe(AuthMethod::Password);
});

test('an unknown identity is rejected and never creates a user', function () {
    $resolved = app(ResolveEntraLogin::class)('oid-nobody', 'nobody@nattura.is');

    expect($resolved)->toBeNull()
        ->and(User::count())->toBe(0);
});

test('the redirect endpoint sends the user to Microsoft with the login hint', function () {
    config()->set('services.azure.client_id', 'test-client');
    config()->set('services.azure.tenant', 'test-tenant');
    config()->set('services.azure.redirect', 'https://mosi.test/auth/entra/callback');

    $response = $this->get(route('entra.redirect', ['login_hint' => 'egill@eigind.is']));

    $response->assertRedirect();

    expect($response->headers->get('Location'))
        ->toContain('login.microsoftonline.com')
        ->toContain('login_hint=egill%40eigind.is');
});

/*
 * The callback route wiring — Socialite is mocked so no network call is made.
 */

test('the callback logs in a matched staff member', function () {
    $user = User::factory()->entra()->create();

    fakeEntraUser('oid-login', $user->email);

    $this->get(route('entra.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user->fresh());
});

test('the callback rejects an unknown identity without authenticating', function () {
    fakeEntraUser('oid-stranger', 'stranger@nattura.is');

    $this->get(route('entra.callback'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

/**
 * Point the Socialite `azure` driver at a canned Entra user, so the callback resolves
 * without contacting Microsoft.
 */
function fakeEntraUser(string $oid, ?string $email): void
{
    $entraUser = Mockery::mock(Laravel\Socialite\Two\User::class);
    $entraUser->shouldReceive('getId')->andReturn($oid);
    $entraUser->shouldReceive('getEmail')->andReturn($email);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($entraUser);

    Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
}
