<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ModulePolicy
{
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    
    public function view(User $user, Module $module): bool
    {
        return true;
    }

    
    public function create(User $user): bool
    {
        return true;
    }

    
    public function update(User $user, Module $module): bool
    {
        return true;
    }

    
    public function delete(User $user, Module $module): bool
    {
        return true;
    }

    
    public function restore(User $user, Module $module): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Module $module): bool
    {
        return false;
    }
}
