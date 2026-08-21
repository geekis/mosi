<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Policies\GroupPolicy;

/**
 * Update a group's translatable name and description. Authorisation (owner, moderator,
 * comms or super-admin) is the caller's responsibility — see {@see GroupPolicy::update()}.
 */
final class UpdateGroup
{
    /**
     * @param  array{name: array{is: string, en: string}, description: array{is: string, en: string}}  $data
     */
    public function __invoke(Group $group, array $data): Group
    {
        $group->setTranslations('name', $data['name']);
        $group->setTranslations('description', $data['description']);
        $group->save();

        return $group;
    }
}
