<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('login is rejected with a generic email error for every failure mode', function (Closure $credentials) {
    User::factory()->create(['email' => 'known@nattura.is']);
    User::factory()->inactive()->create(['email' => 'inactive@nattura.is']);
    User::factory()->entra()->create(['email' => 'uninvited@nattura.is']); // no password yet

    [$email, $password] = $credentials();

    $this->post(route('login.store'), compact('email', 'password'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([
    'unknown email' => [fn () => ['ghost@nattura.is', 'password']],
    'wrong password' => [fn () => ['known@nattura.is', 'wrong-password']],
    'deactivated account' => [fn () => ['inactive@nattura.is', 'password']],
    'uninvited account (no password)' => [fn () => ['uninvited@nattura.is', 'password']],
]);

test('the login form cannot be used to discover who works at NVS', function () {
    User::factory()->create(['email' => 'known@nattura.is']);

    $this->post(route('login.store'), ['email' => 'ghost@nattura.is', 'password' => 'x'])
        ->assertSessionHasErrors('email');
    $unknownEmailError = session('errors')->first('email');

    $this->post(route('login.store'), ['email' => 'known@nattura.is', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
    $wrongPasswordError = session('errors')->first('email');

    // An unknown email and a real email with a wrong password are indistinguishable.
    expect($unknownEmailError)->toBe($wrongPasswordError);
});

test('registration is disabled', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
