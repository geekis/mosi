<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Create a document-library entry and attach its file. The caller authorises (only
 * content managers may add documents) and validates the upload.
 *
 * @phpstan-type DocumentData array{title: array<string, string>|string, description?: array<string, string>|string|null, category?: string|null}
 */
final class UploadDocument
{
    /**
     * @param  DocumentData  $data
     */
    public function __invoke(User $uploader, array $data, UploadedFile $file): Document
    {
        $document = Document::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'is_published' => true,
            'uploaded_by' => $uploader->id,
        ]);

        $document->addMedia($file->getRealPath())
            ->usingFileName($file->getClientOriginalName())
            ->toMediaCollection('file');

        return $document;
    }
}
