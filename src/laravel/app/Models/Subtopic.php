<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subtopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'theory',
        'task',
        'order',
        'theme_id',
    ];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(UserProgressSubtopic::class, 'subtopic_id');
    }
}
