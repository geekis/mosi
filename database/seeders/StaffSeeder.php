<?php

namespace Database\Seeders;

use App\Actions\Staff\EnrolIntoGroups;
use App\Enums\AuthMethod;
use App\Enums\GroupRole;
use App\Models\Group;
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

        $this->seedGroupsAndFollows();
    }

    private function seedGroupsAndFollows(): void
    {
        // Auto-enrol everyone into their department and the org-wide group, exactly as the
        // staff sync would — this creates the department groups from the seeded org data.
        $enrol = app(EnrolIntoGroups::class);
        User::query()->each(fn (User $user) => $enrol($user));

        // A spread of interest groups with random members, including a closed and a secret
        // one, so visibility scopes have something to hide.
        Group::factory()->count(6)->create()->each($this->addRandomMembers(...));
        Group::factory()->closed()->create()->each($this->addRandomMembers(...));
        Group::factory()->secret()->create()->each($this->addRandomMembers(...));

        // A realistic follow graph: each active user follows a handful of others.
        $activeIds = User::query()->where('is_active', true)->pluck('id');

        User::query()->where('is_active', true)->each(function (User $user) use ($activeIds): void {
            $candidates = $activeIds->reject(fn (int $id): bool => $id === $user->id);
            $targets = $candidates->random(min(random_int(3, 10), $candidates->count()));
            $user->following()->syncWithoutDetaching($targets->all());
        });
    }

    private function addRandomMembers(Group $group): void
    {
        $members = User::query()->where('is_active', true)
            ->inRandomOrder()->limit(random_int(8, 40))->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => ['role' => GroupRole::Member->value]]);

        $group->members()->syncWithoutDetaching($members->all());
    }
}
