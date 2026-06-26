<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatAssessment extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'course_id',
        'direction',
        'overall_level',
        'stop_reason',
        'questions_answered',
        'weak_topics',
        'strong_topics',
        'recommendation',
        'practical_skipped',
        'practical_feedback',
        'finished_at',
    ];

    protected $casts = [
        'weak_topics'        => 'array',
        'strong_topics'      => 'array',
        'practical_skipped'  => 'boolean',
        'practical_feedback' => 'array',
        'finished_at'        => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function competenceScores(): HasMany
    {
        return $this->hasMany(CatCompetenceScore::class);
    }

    public function characteristicScores(): HasMany
    {
        return $this->hasMany(CatCharacteristicScore::class);
    }
}
