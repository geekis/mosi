<?php

declare(strict_types=1);

use App\Filament\Pages\StaffSync;
use App\Models\User;
use Spatie\Permission\Models\Role;

function withRoleName(User $user, string $role): User
{
    Role::findOrCreate($role, 'web');
    $user->load('roles');
    $user->assignRole($role);

    return $user;
}

test('an employee cannot reach the admin panel', function () {
    $employee = User::factory()->create();

    $this->actingAs($employee)->get('/admin')->assertForbidden();
});

test('a super-admin can reach the admin panel', function () {
    $admin = withRoleName(User::factory()->create(['locale' => 'en']), 'super-admin');

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('a comms user can reach the admin panel', function () {
    $comms = withRoleName(User::factory()->create(['locale' => 'en']), 'comms');

    $this->actingAs($comms)->get('/admin')->assertOk();
});

test('only a super-admin can open the staff sync page', function () {
    $admin = withRoleName(User::factory()->create(), 'super-admin');
    $comms = withRoleName(User::factory()->create(), 'comms');

    $this->actingAs($admin);
    expect(StaffSync::canAccess())->toBeTrue();

    $this->actingAs($comms);
    expect(StaffSync::canAccess())->toBeFalse();
});

test('a comms user can list pages but an employee cannot', function () {
    $comms = withRoleName(User::factory()->create(['locale' => 'en']), 'comms');
    $employee = User::factory()->create(['locale' => 'en']);

    $this->actingAs($comms)->get('/admin/pages')->assertOk();
    $this->actingAs($employee)->get('/admin/pages')->assertForbidden();
});
