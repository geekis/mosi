<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\User;
use DomainException;

/**
 * Create a staff-started group (interest or project) and make the creator its owner.
 * Department groups are excluded — they mirror the org chart and come only from the
 * staff sync (.ai/rules/database.md).
 */
final class CreateGroup
{
    /**
     * @param  array{name: array{is: string, en: string}, description?: array{is: string, en: string}, type: GroupType, visibility: GroupVisibility}  $data
     */
    public function __invoke(User $creator, array $data): Group
    {
        if ($data['type'] === GroupType::Department) {
            throw new DomainException('Department groups are created by the staff sync, not by hand.');
        }

        $group = Group::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? ['is' => '', 'en' => ''],
            'type' => $data['type'],
            'visibility' => $data['visibility'],
            'created_by' => $creator->id,
        ]);

        $group->members()->attach($creator->id, ['role' => GroupRole::Owner->value]);

        return $group;
    }
}
