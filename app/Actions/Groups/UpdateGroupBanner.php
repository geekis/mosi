<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Http\UploadedFile;

/**
 * Replace a group's banner image. The collection is single-file, so a new upload evicts
 * the previous one. The caller validates that the upload is an image within the size
 * limit and is authorised to edit the group before invoking this.
 */
final class UpdateGroupBanner
{
    public function __invoke(Group $group, UploadedFile $file): void
    {
        $group->addMedia($file->getRealPath())
            ->usingName('banner')
            ->usingFileName('banner.'.$file->getClientOriginalExtension())
            ->toMediaCollection('banner');
    }
}
