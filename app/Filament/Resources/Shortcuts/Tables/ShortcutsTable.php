<?php

namespace App\Filament\Resources\Shortcuts\Tables;

use App\Models\Shortcut;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShortcutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                IconColumn::make('icon')
                    ->label(__('Icon'))
                    // Blade Icons (used by Filament) needs the heroicon set prefix,
                    // unlike Flux which accepts the bare name in the front-end menu.
                    ->icon(fn (Shortcut $record): string => 'heroicon-o-'.$record->iconName()),
                TextColumn::make('label')
                    ->label(__('Label'))
                    ->searchable(),
                TextColumn::make('url')
                    ->label(__('URL'))
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('category')
                    ->label(__('Category'))
                    ->badge()
                    ->placeholder('—'),
                IconColumn::make('opens_in_new_tab')
                    ->label(__('New tab'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
