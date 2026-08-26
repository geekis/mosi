<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Remove a file or gallery item from a group. Allowed for the member who uploaded it or a
 * group manager (owner/moderator/comms/super-admin). If the uploader is unknown (legacy
 * media without the custom property), only a manager may delete it.
 */
final class DeleteGroupMedia
{
    public function __invoke(User $user, Group $group, Media $media): void
    {
        if ($media->model_type !== Group::class
            || (int) $media->model_id !== $group->id
            || ! in_array($media->collection_name, ['files', 'gallery'], true)) {
            throw new DomainException('That attachment does not belong to this group.');
        }

        $uploaderId = $media->getCustomProperty('uploaded_by');
        $isUploader = $uploaderId !== null && (int) $uploaderId === $user->id;
        $isManager = Gate::forUser($user)->allows('update', $group);

        if (! $isUploader && ! $isManager) {
            throw new DomainException('You are not allowed to delete this attachment.');
        }

        $media->delete();
    }
}
