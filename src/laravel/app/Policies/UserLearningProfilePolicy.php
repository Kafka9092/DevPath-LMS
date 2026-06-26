<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserLearningProfile;
use Illuminate\Auth\Access\Response;

class UserLearningProfilePolicy
{
    
    public function viewAny(User $user): bool
    {
        return false;
    }

    
    public function view(User $user, UserLearningProfile $userLearningProfile): bool
    {
        return false;
    }

    
    public function create(User $user): bool
    {
        return false;
    }

    
    public function update(User $user, UserLearningProfile $userLearningProfile): bool
    {
        return false;
    }

    
    public function delete(User $user, UserLearningProfile $userLearningProfile): bool
    {
        return false;
    }

    
    public function restore(User $user, UserLearningProfile $userLearningProfile): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, UserLearningProfile $userLearningProfile): bool
    {
        return false;
    }
}
