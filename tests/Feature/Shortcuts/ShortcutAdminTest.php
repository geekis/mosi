<?php

declare(strict_types=1);

use App\Models\Shortcut;
use App\Models\User;
use Spatie\Permission\Models\Role;

function withShortcutRole(User $user, string $role): User
{
    Role::findOrCreate($role, 'web');
    $user->load('roles');
    $user->assignRole($role);

    return $user;
}

test('admins and content managers can manage shortcuts, employees cannot', function () {
    $admin = withShortcutRole(User::factory()->create(), 'super-admin');
    $comms = withShortcutRole(User::factory()->create(), 'comms');
    $employee = User::factory()->create();
    $shortcut = Shortcut::factory()->create();

    expect($admin->can('create', Shortcut::class))->toBeTrue()
        ->and($comms->can('update', $shortcut))->toBeTrue()
        ->and($employee->can('create', Shortcut::class))->toBeFalse()
        ->and($employee->can('update', $shortcut))->toBeFalse();
});

test('a comms user can list shortcuts but an employee cannot', function () {
    $comms = withShortcutRole(User::factory()->create(['locale' => 'en']), 'comms');
    $employee = User::factory()->create(['locale' => 'en']);

    // A row with a picked icon exercises the table's icon column, which must resolve
    // the Blade Icons heroicon set rather than the bare Flux name.
    Shortcut::factory()->create(['icon' => 'clock']);

    $this->actingAs($comms)->get('/admin/shortcuts')->assertOk();
    $this->actingAs($employee)->get('/admin/shortcuts')->assertForbidden();
});
