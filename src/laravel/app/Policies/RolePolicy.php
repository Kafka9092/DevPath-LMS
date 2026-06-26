<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    
    public function viewAny(User $user): bool
    {
        return false;
    }

    
    public function view(User $user, Role $role): bool
    {
        return false;
    }

    
    public function create(User $user): bool
    {
        return false;
    }

    
    public function update(User $user, Role $role): bool
    {
        return false;
    }

    
    public function delete(User $user, Role $role): bool
    {
        return false;
    }

    
    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }
}
