<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrInterviewMessage extends Model
{
    protected $fillable = [
        'interview_id',
        'role',
        'content',
        'has_code_task',
        'code_snippet',
    ];

    protected $casts = [
        'has_code_task' => 'boolean',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(HrInterview::class, 'interview_id');
    }
}
