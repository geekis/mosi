<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| Auth browser tests (opt-in)
|--------------------------------------------------------------------------
|
| End-to-end coverage of the two interim login paths (see .ai/rules/auth.md). These
| drive a real Chromium via Playwright and are NOT part of `php artisan test`. Run with:
|
|   npm install
|   npx playwright install chromium
|   php artisan test tests/Browser
|
| Browser tests hit the app over HTTP, so they need a shared (file, not :memory:) test
| database — configure a dedicated connection before running.
|
*/

uses(RefreshDatabase::class);

it('logs a staff member in and lands on the dashboard', function () {
    $user = User::factory()->create();

    visit('/login')
        ->assertNoJavaScriptErrors()
        ->fill('email', $user->email)
        ->press('Continue')
        ->fill('password', 'password')
        ->press('Log in')
        ->assertPathIs('/dashboard');
});

it('renders the login page cleanly at 375px', function () {
    visit('/login')
        ->resize(375, 812)
        ->assertNoJavaScriptErrors()
        ->assertSee('Log in');
});

it('accepts an invitation, sets a password, and signs in', function () {
    $user = User::factory()->entra()->create(); // no password yet
    $token = Password::broker('invitations')->createToken($user);

    visit(route('invitation.accept', ['token' => $token, 'email' => $user->email]))
        ->assertNoJavaScriptErrors()
        ->fill('password', 'a-good-password')
        ->fill('password_confirmation', 'a-good-password')
        ->press('Set password and sign in')
        ->assertPathIs('/dashboard');
});
