<?php

namespace App\Filament\Resources\Competences\Pages;

use App\Filament\Resources\Competences\CompetenceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCompetence extends ViewRecord
{
    protected static string $resource = CompetenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
