<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Http\UploadedFile;

/**
 * Replace an event's cover image. The collection is single-file, so a new upload evicts
 * the previous one. The caller validates that the upload is an image within the size
 * limit and is authorised to edit the event before invoking this.
 */
final class UpdateEventCover
{
    public function __invoke(Event $event, UploadedFile $file): void
    {
        $event->addMedia($file->getRealPath())
            ->usingName('cover')
            ->usingFileName('cover.'.$file->getClientOriginalExtension())
            ->toMediaCollection('cover');
    }
}
