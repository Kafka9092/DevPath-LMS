<?php

namespace App\Policies;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ThemePolicy
{
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    
    public function view(User $user, Theme $theme): bool
    {
        return true;
    }

    
    public function create(User $user): bool
    {
        return true;
    }

    
    public function update(User $user, Theme $theme): bool
    {
        return true;
    }

    
    public function delete(User $user, Theme $theme): bool
    {
        return true;
    }

    
    public function restore(User $user, Theme $theme): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Theme $theme): bool
    {
        return false;
    }
}
