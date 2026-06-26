<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProgressSubtopic extends Model
{
    use HasFactory;

    protected $table = 'user_progress_subtopics';

    protected $fillable = [
        'user_id',
        'course_id',
        'subtopic_id',
        'delivery_mode',
        'generated_theory',
        'task_title',
        'task_description',
        'submitted_code',
        'lesson_score',
        'lesson_feedback',
        'lesson_review',
        'hints_used',
        'mentor_conduct_warnings',
        'mentor_chat_blocked',
        'theory_part_index',
        'theory_complete',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'theory_complete'  => 'boolean',
        'is_completed'   => 'boolean',
        'completed_at'   => 'datetime',
        'lesson_review'  => 'array',
        'lesson_score'   => 'integer',
        'hints_used'              => 'integer',
        'mentor_conduct_warnings' => 'integer',
        'mentor_chat_blocked'     => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function subtopic(): BelongsTo
    {
        return $this->belongsTo(Subtopic::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeForUserAndCourse($query, int $userId, int $courseId)
    {
        return $query->where('user_id', $userId)->where('course_id', $courseId);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'is_completed'   => true,
            'completed_at'   => now(),
        ]);
    }

    public function shouldShowTheory(): bool
    {
        return $this->delivery_mode === 'full';
    }
}
