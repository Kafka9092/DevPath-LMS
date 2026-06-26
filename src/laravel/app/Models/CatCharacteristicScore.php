<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatCharacteristicScore extends Model
{
    public const SOURCE_PRACTICAL = 'practical_task';

    protected $fillable = [
        'cat_assessment_id',
        'characteristic_id',
        'score',
        'source',
        'feedback',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(CatAssessment::class, 'cat_assessment_id');
    }

    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(Characteristic::class);
    }
}
