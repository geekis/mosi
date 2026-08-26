<?php

namespace App\Filament\Resources\Shortcuts\Pages;

use App\Filament\Resources\Shortcuts\ShortcutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShortcuts extends ListRecords
{
    protected static string $resource = ShortcutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
