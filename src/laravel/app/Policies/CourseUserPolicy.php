<?php

namespace App\Policies;

use App\Models\Course_user;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CourseUserPolicy
{
    
    public function viewAny(User $user): bool
    {
        return false;
    }

    
    public function view(User $user, Course_user $courseUser): bool
    {
        return false;
    }

    
    public function create(User $user): bool
    {
        return false;
    }

    
    public function update(User $user, Course_user $courseUser): bool
    {
        return false;
    }

    
    public function delete(User $user, Course_user $courseUser): bool
    {
        return false;
    }

    
    public function restore(User $user, Course_user $courseUser): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Course_user $courseUser): bool
    {
        return false;
    }
}
