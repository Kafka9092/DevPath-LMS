<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeReview extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'detected_language',
        'code',
        'sonar_project_key',
        'sonar_metrics',
        'sonar_issues',
        'ai_evaluation',
        'overall_score',
    ];

    protected function casts(): array
    {
        return [
            'sonar_metrics'  => 'array',
            'sonar_issues'   => 'array',
            'ai_evaluation'  => 'array',
            'overall_score'  => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
