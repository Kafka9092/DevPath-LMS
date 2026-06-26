<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
        'direction_id',
        'level_id',
        'course_template_id',
        'template_structure_hash',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_users')
            ->withPivot(['status', 'progress', 'started_at'])
            ->withTimestamps();
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function courseTemplate(): BelongsTo
    {
        return $this->belongsTo(CourseTemplate::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'course_id');
    }

    public function progressSubtopics(): HasMany
    {
        return $this->hasMany(UserProgressSubtopic::class, 'course_id');
    }

    public function userProfiles(): HasMany
    {
        return $this->hasMany(UserLearningProfile::class, 'course_id');
    }
}
