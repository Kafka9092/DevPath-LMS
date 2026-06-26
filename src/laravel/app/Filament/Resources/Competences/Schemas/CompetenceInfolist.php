<?php

namespace App\Filament\Resources\Competences\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CompetenceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('name')
                    ->label('Название'),
                TextEntry::make('category')
                    ->label('Категория')
                    ->placeholder('-'),
                IconEntry::make('beginner_level')
                    ->label('Уровень Beginner')
                    ->boolean(),
                IconEntry::make('junior_level')
                    ->label('Уровень Junior')
                    ->boolean(),
                IconEntry::make('middle_level')
                    ->label('Уровень Middle')
                    ->boolean(),
                IconEntry::make('senior_level')
                    ->label('Уровень Senior')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
