<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        // Metadata only — the file itself is uploaded on the front-end /documents page.
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('Title'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('category')
                    ->label(__('Category'))
                    ->maxLength(255),
                Toggle::make('is_published')
                    ->label(__('Published'))
                    ->default(true),
            ]);
    }
}
