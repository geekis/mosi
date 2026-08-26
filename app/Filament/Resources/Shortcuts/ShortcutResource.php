<?php

namespace App\Filament\Resources\Shortcuts;

use App\Filament\Resources\Shortcuts\Pages\CreateShortcut;
use App\Filament\Resources\Shortcuts\Pages\EditShortcut;
use App\Filament\Resources\Shortcuts\Pages\ListShortcuts;
use App\Filament\Resources\Shortcuts\Schemas\ShortcutForm;
use App\Filament\Resources\Shortcuts\Tables\ShortcutsTable;
use App\Models\Shortcut;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShortcutResource extends Resource
{
    protected static ?string $model = Shortcut::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function getModelLabel(): string
    {
        return __('Shortcut');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Shortcuts');
    }

    public static function form(Schema $schema): Schema
    {
        return ShortcutForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShortcutsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShortcuts::route('/'),
            'create' => CreateShortcut::route('/create'),
            'edit' => EditShortcut::route('/{record}/edit'),
        ];
    }
}
