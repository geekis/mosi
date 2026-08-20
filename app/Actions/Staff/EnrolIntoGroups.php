<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Auto-enrol a synced staff member into their department group and the org-wide "Allir
 * starfsmenn" group, creating either on first sight. The org-wide group is a real group,
 * not a special case — enrolment is uniform; only its name is supplied here because the
 * payload carries the parent org id but not its name (.ai/rules/database.md, staff-api.md).
 *
 * A manager (managerFlag === 'Y') owns their own department group and is granted the
 * app-wide `group-owner` role.
 */
final class EnrolIntoGroups
{
    public function __invoke(User $user): void
    {
        $department = $this->departmentGroup($user->organization_id, $user->organization_name);

        if ($department !== null) {
            $department->members()->syncWithoutDetaching([
                $user->id => ['role' => $user->is_manager ? GroupRole::Owner->value : GroupRole::Member->value],
            ]);
        }

        $orgWide = $this->orgWideGroup($user->parent_organization_id);

        if ($orgWide !== null) {
            $orgWide->members()->syncWithoutDetaching([
                $user->id => ['role' => GroupRole::Member->value],
            ]);
        }

        if ($user->is_manager) {
            Role::findOrCreate('group-owner', 'web');

            if (! $user->hasAnyRoleName('group-owner')) {
                $user->load('roles');
                $user->assignRole('group-owner');
            }
        }
    }

    private function departmentGroup(?string $organizationId, ?string $organizationName): ?Group
    {
        if ($organizationId === null) {
            return null;
        }

        return Group::firstOrCreate(
            ['external_organization_id' => $organizationId],
            [
                'name' => [
                    'is' => $organizationName ?? "Deild {$organizationId}",
                    'en' => $organizationName ?? "Department {$organizationId}",
                ],
                'type' => GroupType::Department,
                'visibility' => GroupVisibility::Open,
            ],
        );
    }

    private function orgWideGroup(?string $parentOrganizationId): ?Group
    {
        if ($parentOrganizationId === null) {
            return null;
        }

        return Group::firstOrCreate(
            ['external_organization_id' => $parentOrganizationId],
            [
                'name' => ['is' => 'Allir starfsmenn', 'en' => 'All staff'],
                'type' => GroupType::Department,
                'visibility' => GroupVisibility::Open,
            ],
        );
    }
}
