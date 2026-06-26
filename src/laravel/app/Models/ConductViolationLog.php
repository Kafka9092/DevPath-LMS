<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConductViolationLog extends Model
{
    public const SOURCE_MENTOR_CHAT = 'mentor_chat';

    public const SOURCE_HR_INTERVIEW = 'hr_interview';

    public const CATEGORY_JAILBREAK = 'jailbreak';

    public const CATEGORY_ABUSE = 'abuse';

    public const ACTION_WARN = 'warn';

    public const ACTION_BLOCK = 'block';

    public const ACTION_TERMINATE = 'terminate';

    protected $fillable = [
        'user_id',
        'source',
        'category',
        'action',
        'warning_number',
        'message_excerpt',
        'classifier_reason',
        'classifier_confidence',
        'context',
    ];

    protected $casts = [
        'classifier_confidence' => 'float',
        'context'               => 'array',
        'warning_number'        => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_MENTOR_CHAT  => 'Чат ментора',
            self::SOURCE_HR_INTERVIEW => 'Собеседования AI HR',
            default                   => $this->source,
        };
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_JAILBREAK => 'Обход правил',
            self::CATEGORY_ABUSE     => 'Токсичность',
            default                  => $this->category,
        };
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_WARN      => 'Предупреждение',
            self::ACTION_BLOCK     => 'Блок чата',
            self::ACTION_TERMINATE => 'Завершение',
            default                => $this->action,
        };
    }
}
