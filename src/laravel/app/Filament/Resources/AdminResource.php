<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class AdminResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
