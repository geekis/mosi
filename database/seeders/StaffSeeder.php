<?php

namespace Database\Seeders;

use App\Enums\AuthMethod;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ~260 staff across the real NVS organisations, mirroring the production shape:
 * a ~75% Entra / ~25% personal-email split, ~10% managers, a handful of disabled
 * (departed) accounts, and a couple with no contact details. Realistic data surfaces
 * pagination, sorting and N+1 bugs that "User 1, User 2" would hide (.ai/rules/database.md).
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);

        User::factory()->count(20)->entra()->manager()->create();
        User::factory()->count(180)->entra()->create();
        User::factory()->count(50)->passwordAuth()->create();
        User::factory()->count(8)->inactive()->create();
        User::factory()->count(2)->withoutEmail()->create();

        // Roles are assigned in a single call per user with `roles` eager-loaded, so
        // Spatie's role access does not trip the lazy-loading guard. Everyone is an
        // employee; managers also own their organisation's group.
        User::query()->with('roles')->each(function (User $user): void {
            $user->assignRole($user->is_manager ? ['employee', 'group-owner'] : ['employee']);
        });

        // A small comms team and one super-admin (2FA is enforced for these in 1D).
        User::query()->where('auth_method', AuthMethod::Entra->value)
            ->with('roles')->inRandomOrder()->limit(2)->get()
            ->each(fn (User $user) => $user->assignRole('comms'));

        User::query()->where('auth_method', AuthMethod::Entra->value)
            ->with('roles')->first()?->assignRole('super-admin');
    }
}
