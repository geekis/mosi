<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * The four Mosi roles (see .ai/rules/auth.md). Idempotent — safe to run repeatedly.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super-admin', 'comms', 'group-owner', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
