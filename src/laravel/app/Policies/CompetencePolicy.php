<?php

namespace App\Policies;

use App\Models\Competence;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompetencePolicy
{
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    
    public function view(User $user, Competence $competence): bool
    {
        return true;
    }

    
    public function create(User $user): bool
    {
        return true;
    }

    
    public function update(User $user, Competence $competence): bool
    {
        return true;
    }

    
    public function delete(User $user, Competence $competence): bool
    {
        return true;
    }

    
    public function restore(User $user, Competence $competence): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, Competence $competence): bool
    {
        return false;
    }
}
