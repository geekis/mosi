<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document): BinaryFileResponse
    {
        Gate::authorize('view', $document);

        $media = $document->getFirstMedia('file');

        abort_if($media === null, 404);

        return response()->download($media->getPath(), $media->file_name);
    }
}
