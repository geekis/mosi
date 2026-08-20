<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Password;

it('renders the login page in Icelandic', function () {
    $this->withSession(['locale' => 'is'])
        ->get(route('login'))
        ->assertOk()
        ->assertSee('Skráðu þig inn á aðganginn þinn');
});

it('renders the login page in English', function () {
    $this->withSession(['locale' => 'en'])
        ->get(route('login'))
        ->assertOk()
        ->assertSee('Log in to your account');
});

it('renders the invitation page in Icelandic', function () {
    $user = User::factory()->entra()->create();
    $token = Password::broker('invitations')->createToken($user);

    $this->withSession(['locale' => 'is'])
        ->get(route('invitation.accept', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertSee('Stilltu lykilorðið þitt');
});

it('renders the invitation page in English', function () {
    $user = User::factory()->entra()->create();
    $token = Password::broker('invitations')->createToken($user);

    $this->withSession(['locale' => 'en'])
        ->get(route('invitation.accept', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertSee('Set a password to activate your account');
});
