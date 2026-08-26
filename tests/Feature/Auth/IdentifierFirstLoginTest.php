<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    // Production default, made explicit so the branch is stable regardless of the
    // machine's env (the test tenant overrides this to eigind.is).
    config()->set('services.azure.entra_domain', 'nattura.is');
});

test('the login page asks only for an email first, with no password field', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-test="continue-button"', false)
        ->assertDontSee('data-test="login-button"', false);
});

test('a Microsoft-domain email is sent to Entra with a login hint', function () {
    $this->post(route('login.identify'), ['email' => 'starf@nattura.is'])
        ->assertRedirect(route('entra.redirect', ['login_hint' => 'starf@nattura.is']));
});

test('a non-Microsoft email reveals the password field on the login page', function () {
    $this->followingRedirects()
        ->post(route('login.identify'), ['email' => 'ranger@gmail.com'])
        ->assertOk()
        ->assertSee('data-test="login-button"', false)
        ->assertSee('ranger@gmail.com');
});

test('the identify step cannot enumerate staff: unknown and known behave identically', function () {
    User::factory()->entra()->create(['email' => 'known@nattura.is']);
    User::factory()->create(['email' => 'known@gmail.com']);

    // Microsoft-domain: both a real and a made-up address redirect to Entra the same way.
    // Only the login_hint (the echoed input) differs, which reveals nothing about existence.
    $known = $this->post(route('login.identify'), ['email' => 'known@nattura.is']);
    $ghost = $this->post(route('login.identify'), ['email' => 'ghost@nattura.is']);

    expect(parse_url((string) $ghost->headers->get('Location'), PHP_URL_PATH))
        ->toBe(parse_url((string) $known->headers->get('Location'), PHP_URL_PATH));

    // Personal domain: both bounce back to reveal the password field the same way.
    $knownPersonal = $this->post(route('login.identify'), ['email' => 'known@gmail.com']);
    $ghostPersonal = $this->post(route('login.identify'), ['email' => 'ghost@gmail.com']);

    expect($ghostPersonal->headers->get('Location'))->toBe($knownPersonal->headers->get('Location'))
        ->and($knownPersonal->headers->get('Location'))->toBe(route('login'));
});

test('the identify step validates the email', function () {
    $this->from(route('login'))
        ->post(route('login.identify'), ['email' => 'not-an-email'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

test('the configured entra domain is what decides the branch', function () {
    config()->set('services.azure.entra_domain', 'eigind.is');

    $this->post(route('login.identify'), ['email' => 'egill@eigind.is'])
        ->assertRedirect(route('entra.redirect', ['login_hint' => 'egill@eigind.is']));

    // With eigind.is configured, a nattura.is address is now treated as a password account.
    $this->post(route('login.identify'), ['email' => 'someone@nattura.is'])
        ->assertRedirect(route('login'));
});
