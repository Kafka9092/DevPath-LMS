<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserProgressSubtopic;
use Illuminate\Auth\Access\Response;

class UserSubtopicProgressPolicy
{
    
    public function viewAny(User $user): bool
    {
        return false;
    }

    
    public function view(User $user, UserProgressSubtopic $userSubtopicProgress): bool
    {
        return false;
    }

    
    public function create(User $user): bool
    {
        return false;
    }

    
    public function update(User $user, UserProgressSubtopic $userSubtopicProgress): bool
    {
        return false;
    }

    
    public function delete(User $user, UserProgressSubtopic $userSubtopicProgress): bool
    {
        return false;
    }

    
    public function restore(User $user, UserProgressSubtopic $userSubtopicProgress): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, UserProgressSubtopic $userSubtopicProgress): bool
    {
        return false;
    }
}
