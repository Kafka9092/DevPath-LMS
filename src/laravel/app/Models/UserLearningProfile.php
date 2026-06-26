<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLearningProfile extends Model
{
    use HasFactory;

    protected $table = 'user_learning_profiles';

    protected $fillable = [
        'user_id',
        'course_id',
        'learning_preference',
        'domain_interest',
        'mentor_persona',
        'career_goal',
        'struggle_score',
        'success_streak',
        'last_help_offered_at',
    ];

    protected $casts = [
        'last_help_offered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function prefersPracticeOnly(): bool
    {
        return $this->learning_preference === 'practice_heavy';
    }
}
