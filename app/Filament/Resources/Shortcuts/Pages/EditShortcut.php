<?php

namespace App\Filament\Resources\Shortcuts\Pages;

use App\Filament\Resources\Shortcuts\ShortcutResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShortcut extends EditRecord
{
    protected static string $resource = ShortcutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
