<?php

namespace App\Filament\Resources\ConductViolationLogs;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\ConductViolationLogs\Pages\ListConductViolationLogs;
use App\Filament\Resources\ConductViolationLogs\Tables\ConductViolationLogsTable;
use App\Models\ConductViolationLog;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConductViolationLogResource extends AdminResource
{
    protected static ?string $model = ConductViolationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Журнал нарушений';

    protected static ?string $modelLabel = 'нарушение';

    protected static ?string $pluralModelLabel = 'Журнал нарушений';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'Безопасность';

    public static function table(Table $table): Table
    {
        return ConductViolationLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConductViolationLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
