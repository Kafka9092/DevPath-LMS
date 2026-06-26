<?php

namespace App\Policies;

use App\Models\Direction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DirectionPolicy
{
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    
    public function view(User $user, Direction $direction): bool
    {
        return true;
    }

    
    public function create(User $user): bool
    {
        return true;
    }

    
    public function update(User $user, Direction $direction): bool
    {
        return true;
    }

    
    public function delete(User $user, Direction $direction): bool
    {
        return true;
    }

    
    public function restore(User $user, Direction $direction): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Direction $direction): bool
    {
        return false;
    }
}
