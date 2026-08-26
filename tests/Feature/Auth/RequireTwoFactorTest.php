<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Role;

function withRole(User $user, string $role): User
{
    Role::findOrCreate($role, 'web');
    $user->load('roles');
    $user->assignRole($role);

    return $user;
}

it('forces a privileged user without 2FA to the security page', function () {
    $user = withRole(User::factory()->create(), 'comms');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));
});

it('lets a privileged user with 2FA enabled through', function () {
    $user = withRole(User::factory()->withTwoFactor()->create(), 'super-admin');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('does not force 2FA on a privileged user in the local environment', function () {
    app()['env'] = 'local';
    $user = withRole(User::factory()->create(), 'super-admin');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('does not force 2FA on a regular employee', function () {
    $user = withRole(User::factory()->create(), 'employee');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('does not trap a privileged user on the security page so they can enrol', function () {
    $user = withRole(User::factory()->create(), 'comms');

    $response = $this->actingAs($user)->get(route('security.edit'));

    // It redirects onward (Fortify asks them to confirm their password first), not back
    // to the security page in a loop.
    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain(route('security.edit'));
});
