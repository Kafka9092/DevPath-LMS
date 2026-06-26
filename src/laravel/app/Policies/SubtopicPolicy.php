<?php

namespace App\Policies;

use App\Models\Subtopic;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use PhpParser\Node\Stmt\TraitUse;

class SubtopicPolicy
{
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    
    public function view(User $user, Subtopic $subtopic): bool
    {
        return true;
    }

    
    public function create(User $user): bool
    {
        return true;
    }

    
    public function update(User $user, Subtopic $subtopic): bool
    {
        return true;
    }

    
    public function delete(User $user, Subtopic $subtopic): bool
    {
        return true;
    }

    
    public function restore(User $user, Subtopic $subtopic): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Subtopic $subtopic): bool
    {
        return false;
    }
}
