<?php

namespace App\Policies;

use App\Models\Characteristic;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CharacteristicPolicy
{
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    
    public function view(User $user, Characteristic $characteristic): bool
    {
        return true;
    }

    
    public function create(User $user): bool
    {
        return true;
    }

    
    public function update(User $user, Characteristic $characteristic): bool
    {
        return true;
    }

    
    public function delete(User $user, Characteristic $characteristic): bool
    {
        return true;
    }

    
    public function restore(User $user, Characteristic $characteristic): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Characteristic $characteristic): bool
    {
        return false;
    }
}
