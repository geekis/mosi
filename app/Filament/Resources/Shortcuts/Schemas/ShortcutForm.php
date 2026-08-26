<?php

namespace App\Filament\Resources\Shortcuts\Schemas;

use App\Models\Shortcut;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ShortcutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label(__('Label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label(__('URL'))
                    ->url()
                    ->required()
                    ->maxLength(2048),
                TextInput::make('description')
                    ->label(__('Description'))
                    ->maxLength(255),
                Select::make('icon')
                    ->label(__('Icon'))
                    ->options(collect(Shortcut::ICONS)->mapWithKeys(
                        fn (string $icon): array => [$icon => Str::headline($icon)]
                    ))
                    ->searchable()
                    ->placeholder(__('Default')),
                Textarea::make('svg')
                    ->label(__('Custom icon (SVG)'))
                    ->helperText(__('Paste raw <svg> markup to use your own icon. It is sanitised on save and overrides the icon above. Leave blank to use the icon.'))
                    ->rows(4)
                    ->autosize(),
                TextInput::make('category')
                    ->label(__('Category'))
                    ->helperText(__('Groups shortcuts into sections in the mega menu.'))
                    ->maxLength(255),
                Toggle::make('opens_in_new_tab')
                    ->label(__('Open in a new tab'))
                    ->default(true),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
            ]);
    }
}
