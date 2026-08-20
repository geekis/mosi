<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Role;

function asSuperAdmin(User $user): User
{
    Role::findOrCreate('super-admin', 'web');
    $user->load('roles');
    $user->assignRole('super-admin');

    return $user;
}

test('a colleague can view an active profile', function () {
    expect(User::factory()->create()->can('view', User::factory()->create()))->toBeTrue();
});

test('a colleague cannot view an inactive profile', function () {
    $viewer = User::factory()->create();
    $inactive = User::factory()->inactive()->create();

    expect($viewer->can('view', $inactive))->toBeFalse();
});

test('a user can always view their own profile, even when inactive', function () {
    $user = User::factory()->inactive()->create();

    expect($user->can('view', $user))->toBeTrue();
});

test('a super-admin can view an inactive profile', function () {
    $admin = asSuperAdmin(User::factory()->create());
    $inactive = User::factory()->inactive()->create();

    expect($admin->can('view', $inactive))->toBeTrue();
});

test('only the owner or a super-admin can update a profile', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $admin = asSuperAdmin(User::factory()->create());

    expect($owner->can('update', $owner))->toBeTrue()
        ->and($other->can('update', $owner))->toBeFalse()
        ->and($admin->can('update', $owner))->toBeTrue();
});
