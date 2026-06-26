<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatCompetenceScore extends Model
{
    protected $fillable = [
        'cat_assessment_id',
        'competence_id',
        'score',
        'mastered',
        'tested_at_level',
    ];

    protected $casts = [
        'mastered' => 'boolean',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(CatAssessment::class, 'cat_assessment_id');
    }

    public function competence(): BelongsTo
    {
        return $this->belongsTo(Competence::class);
    }
}
