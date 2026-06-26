<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrInterview extends Model
{
    protected $fillable = [
        'user_id',
        'direction',
        'level',
        'status',
        'conduct_warnings',
        'verdict',
        'duration_seconds',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'verdict'          => 'array',
        'conduct_warnings' => 'integer',
        'started_at'       => 'datetime',
        'finished_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HrInterviewMessage::class, 'interview_id');
    }
}
