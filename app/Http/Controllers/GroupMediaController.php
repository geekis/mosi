<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Stream a group's members-only file or gallery item. These live on the private disk and
 * have no public URL, so this is the only way to reach them — and only members may
 * (.ai/rules/auth.md). Assumes a local-driver private disk (uses the file's absolute path,
 * which lets Symfony honour HTTP Range requests so gallery video can be scrubbed).
 */
class GroupMediaController extends Controller
{
    public function __invoke(Group $group, Media $media): BinaryFileResponse
    {
        // Ownership first: a mismatched group, a foreign media id, or a non-members-only
        // collection (e.g. the public banner) all 404 — existence is never revealed to a
        // member of the wrong group before the membership check runs.
        abort_unless(
            $media->model_type === Group::class
                && (int) $media->model_id === $group->id
                && in_array($media->collection_name, ['files', 'gallery'], true),
            404,
        );

        Gate::authorize('viewMemberContent', $group);

        // Files download as an attachment; gallery images/video render inline.
        if ($media->collection_name === 'files') {
            return response()->download($media->getPath(), $media->file_name);
        }

        return response()->file($media->getPath(), ['Content-Type' => $media->mime_type]);
    }
}
