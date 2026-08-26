<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attach an image or video to a group's members-only `gallery` collection (private disk).
 * Any group member may upload; the mime type and size are enforced here.
 */
final class UploadGroupMedia
{
    /** Maximum media size in bytes (100 MB — videos are large). */
    public const int MAX_BYTES = 100 * 1024 * 1024;

    public function __invoke(User $uploader, Group $group, UploadedFile $file): Media
    {
        if (! $group->hasMember($uploader)) {
            throw new DomainException('You must be a member of the group to upload media.');
        }

        if (! in_array((string) $file->getMimeType(), Group::GALLERY_MIME_TYPES, true)) {
            throw new DomainException('Only images and videos can be added to the gallery.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new DomainException('The file is too large.');
        }

        return $group->addMedia($file->getRealPath())
            ->usingFileName($file->getClientOriginalName())
            ->withCustomProperties(['uploaded_by' => $uploader->id])
            ->toMediaCollection('gallery');
    }
}
