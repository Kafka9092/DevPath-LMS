<?php

namespace App\Filament\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationLabel = 'Административная панель';

    protected static ?string $title = 'Административная панель';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
