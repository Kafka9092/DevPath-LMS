<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'github_id', 
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_users')
            ->withPivot(['status', 'progress', 'started_at'])
            ->withTimestamps();
    }

    public function progressSubtopics(): HasMany
    {
        return $this->hasMany(UserProgressSubtopic::class, 'user_id');
    }

    public function learningProfiles(): HasMany
    {
        return $this->hasMany(UserLearningProfile::class, 'user_id');
    }

    public function catAssessments(): HasMany
    {
        return $this->hasMany(CatAssessment::class);
    }

    public function hrInterviews(): HasMany
    {
        return $this->hasMany(HrInterview::class);
    }

    public function codeReviews(): HasMany
    {
        return $this->hasMany(CodeReview::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->avatar)) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    public function deleteAvatarFile(): void
    {
        if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
            Storage::disk('public')->delete($this->avatar);
        }
    }

    public function assignDefaultUserRole(): void
    {
        if ($this->roles()->exists()) {
            return;
        }

        $role = Role::query()
            ->whereIn('name', ['User', 'user'])
            ->orderByRaw("CASE name WHEN 'User' THEN 0 ELSE 1 END")
            ->first();

        if ($role) {
            $this->assignRole($role);

            return;
        }

        $this->assignRole(Role::firstOrCreate([
            'name' => 'User',
            'guard_name' => 'web',
        ]));
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['Admin', 'admin']);
    }

    public function isMethodist(): bool
    {
        return $this->hasAnyRole(['Methodist', 'methodist']);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() || $this->isMethodist();
    }
}
