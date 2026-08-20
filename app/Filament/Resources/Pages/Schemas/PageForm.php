<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('Title'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('body')
                    ->label(__('Body'))
                    ->rows(12)
                    ->required(),
                Toggle::make('is_published')
                    ->label(__('Published'))
                    ->default(true),
            ]);
    }
}
