<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CoursePolicy
{
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    
    public function view(User $user, Course $course): bool
    {
        return true;
    }

    
    public function create(User $user): bool
    {
        return true;
    }

    
    public function update(User $user, Course $course): bool
    {
        return true;
    }

    
    public function delete(User $user, Course $course): bool
    {
        return true;
    }

    
    public function restore(User $user, Course $course): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Course $course): bool
    {
        return false;
    }
}
