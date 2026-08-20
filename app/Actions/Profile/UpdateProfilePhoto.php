<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Replace one of a user's self-service profile images. Both collections are single-file,
 * so adding a new one evicts the previous. The caller validates that the upload is an
 * image within the size limit before invoking this.
 */
final class UpdateProfilePhoto
{
    /** @var list<string> */
    private const array COLLECTIONS = ['avatar', 'banner'];

    public function __invoke(User $user, UploadedFile $file, string $collection): void
    {
        if (! in_array($collection, self::COLLECTIONS, true)) {
            throw new InvalidArgumentException("Unknown profile image collection: {$collection}.");
        }

        $user->addMedia($file->getRealPath())
            ->usingName($collection)
            ->usingFileName($collection.'.'.$file->getClientOriginalExtension())
            ->toMediaCollection($collection);
    }
}
