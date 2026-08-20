<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Password;

function invitationToken(User $user): string
{
    return Password::broker('invitations')->createToken($user);
}

it('renders the set-password page for an invited user', function () {
    $user = User::factory()->entra()->create();

    $this->get(route('invitation.accept', ['token' => invitationToken($user), 'email' => $user->email]))
        ->assertOk()
        ->assertSee($user->email);
});

it('sets the password, signs the user in, and redirects to the dashboard', function () {
    $user = User::factory()->entra()->create(); // no password yet

    $response = $this->post(route('invitation.store'), [
        'token' => invitationToken($user),
        'email' => $user->email,
        'password' => 'a-good-password',
        'password_confirmation' => 'a-good-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user->fresh());

    expect($user->fresh()->password)->not->toBeNull()
        ->and($user->fresh()->password_changed_at)->not->toBeNull();
});

it('lets the user log in with the new password afterwards', function () {
    $user = User::factory()->entra()->create();

    $this->post(route('invitation.store'), [
        'token' => invitationToken($user),
        'email' => $user->email,
        'password' => 'a-good-password',
        'password_confirmation' => 'a-good-password',
    ]);
    auth()->logout();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'a-good-password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('rejects an invalid or expired token and sets no password', function () {
    $user = User::factory()->entra()->create();

    $this->post(route('invitation.store'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'a-good-password',
        'password_confirmation' => 'a-good-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->fresh()->password)->toBeNull();
});
