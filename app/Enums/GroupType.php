<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Department groups are auto-enrolled from the staff sync's organisation data; interest
 * and project groups are created by staff. The org-wide "Allir starfsmenn" group is a
 * real department group, not a special case (.ai/rules/database.md).
 */
enum GroupType: string
{
    case Department = 'department';
    case Interest = 'interest';
    case Project = 'project';

    public function label(): string
    {
        return __("enums.group_type.{$this->value}");
    }
}
