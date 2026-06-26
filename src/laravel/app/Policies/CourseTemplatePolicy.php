<?php

namespace App\Policies;

use App\Models\CourseTemplate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CourseTemplatePolicy
{
    protected function canManageTemplates(User $user): bool
    {
        return $user->isAdmin() || $user->isMethodist();
    }

    public function viewAny(User $user): bool
    {
        return $this->canManageTemplates($user);
    }

    public function view(User $user, CourseTemplate $courseTemplate): bool
    {
        return $this->canManageTemplates($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CourseTemplate $courseTemplate): bool
    {
        return $this->canManageTemplates($user);
    }

    
    public function delete(User $user, CourseTemplate $courseTemplate): bool
    {
        return false;
    }

    
    public function restore(User $user, CourseTemplate $courseTemplate): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, CourseTemplate $courseTemplate): bool
    {
        return false;
    }
}
