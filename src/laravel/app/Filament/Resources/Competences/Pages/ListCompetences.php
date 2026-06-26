<?php

namespace App\Filament\Resources\Competences\Pages;

use App\Filament\Resources\Competences\CompetenceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompetences extends ListRecords
{
    protected static string $resource = CompetenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
