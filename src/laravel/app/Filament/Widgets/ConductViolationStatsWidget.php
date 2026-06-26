<?php

namespace App\Filament\Widgets;

use App\Service\ConductViolationLogger;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ConductViolationStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 0;

    protected ?string $heading = 'Сводка нарушений';

    protected int|string|array $columnSpan = 'full';

    private const DESCRIPTION = 'Все попытки за текущие сутки';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $stats = app(ConductViolationLogger::class)->jailbreakStats();
        $mentor = $stats['mentor'];
        $hr = $stats['hr'];

        return [
            Stat::make('Обход правил сегодня', (string) $stats['today'])
                ->description(self::DESCRIPTION)
                ->color('danger'),
            Stat::make('Чат ментора', (string) $mentor['today'])
                ->description(self::DESCRIPTION)
                ->color('primary'),
            Stat::make('Собеседования AI HR', (string) $hr['today'])
                ->description(self::DESCRIPTION)
                ->color('orange'),
        ];
    }
}
