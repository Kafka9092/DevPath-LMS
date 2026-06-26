<?php

namespace App\Filament\Resources\ConductViolationLogs\Pages;

use App\Filament\Resources\ConductViolationLogs\ConductViolationLogResource;
use App\Filament\Widgets\ConductViolationStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListConductViolationLogs extends ListRecords
{
    protected static string $resource = ConductViolationLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ConductViolationStatsWidget::class,
        ];
    }
}
