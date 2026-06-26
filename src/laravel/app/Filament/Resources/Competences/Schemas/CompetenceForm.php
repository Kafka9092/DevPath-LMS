<?php

namespace App\Filament\Resources\Competences\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompetenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('category')
                    ->label('Категория')
                    ->maxLength(255),
                Toggle::make('beginner_level')
                    ->label('Уровень Beginner'),
                Toggle::make('junior_level')
                    ->label('Уровень Junior'),
                Toggle::make('middle_level')
                    ->label('Уровень Middle'),
                Toggle::make('senior_level')
                    ->label('Уровень Senior'),
            ]);
    }
}
