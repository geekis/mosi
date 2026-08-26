<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attach an office document to a group's members-only `files` collection (private disk).
 * Any group member may upload; the mime type and size are enforced here so the collection
 * never receives anything but allowed documents.
 */
final class UploadGroupFile
{
    /** Maximum file size in bytes (20 MB). */
    public const int MAX_BYTES = 20 * 1024 * 1024;

    public function __invoke(User $uploader, Group $group, UploadedFile $file): Media
    {
        if (! $group->hasMember($uploader)) {
            throw new DomainException('You must be a member of the group to upload files.');
        }

        if (! in_array((string) $file->getMimeType(), Group::FILE_MIME_TYPES, true)) {
            throw new DomainException('This file type is not allowed.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new DomainException('The file is too large.');
        }

        return $group->addMedia($file->getRealPath())
            ->usingFileName($file->getClientOriginalName())
            ->withCustomProperties(['uploaded_by' => $uploader->id])
            ->toMediaCollection('files');
    }
}
