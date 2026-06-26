<?php

namespace App\Policies;

use App\Models\Level;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LevelPolicy
{
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    
    public function view(User $user, Level $level): bool
    {
        return true;
    }

    
    public function create(User $user): bool
    {
        return true;
    }

    
    public function update(User $user, Level $level): bool
    {
        return true;
    }

    
    public function delete(User $user, Level $level): bool
    {
        return true;
    }

    
    public function restore(User $user, Level $level): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Level $level): bool
    {
        return false;
    }
}
