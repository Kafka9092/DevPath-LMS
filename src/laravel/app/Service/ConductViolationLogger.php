<?php

namespace App\Service;

use App\Models\ConductViolationLog;
use App\Models\HrInterview;
use App\Models\UserProgressSubtopic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConductViolationLogger
{
    /**
     * @param array{category?: string, confidence?: float, reason?: string} $classification
     */
    public function logMentorViolation(
        UserProgressSubtopic $progress,
        string $subtopicTitle,
        string $userMessage,
        array $classification,
        string $reason,
        string $conductAction,
        int $warningNumber,
    ): void {
        $category = $reason === 'jailbreak'
            ? ConductViolationLog::CATEGORY_JAILBREAK
            : ConductViolationLog::CATEGORY_ABUSE;

        $this->persist(
            userId: (int) $progress->user_id,
            source: ConductViolationLog::SOURCE_MENTOR_CHAT,
            category: $category,
            action: $this->mapMentorAction($conductAction),
            warningNumber: $warningNumber,
            userMessage: $userMessage,
            classification: $classification,
            context: [
                'course_id'     => (int) $progress->course_id,
                'subtopic_id'   => (int) $progress->subtopic_id,
                'subtopic_title'=> $subtopicTitle,
            ],
        );
    }

    /**
     * @param array{category?: string, confidence?: float, reason?: string} $classification
     */
    public function logHrViolation(
        HrInterview $interview,
        string $userMessage,
        array $classification,
        string $reason,
        string $conductAction,
        int $warningNumber,
    ): void {
        $category = $reason === 'jailbreak'
            ? ConductViolationLog::CATEGORY_JAILBREAK
            : ConductViolationLog::CATEGORY_ABUSE;

        $this->persist(
            userId: $interview->user_id !== null ? (int) $interview->user_id : null,
            source: ConductViolationLog::SOURCE_HR_INTERVIEW,
            category: $category,
            action: $this->mapHrAction($conductAction),
            warningNumber: $warningNumber,
            userMessage: $userMessage,
            classification: $classification,
            context: [
                'interview_id' => (int) $interview->id,
                'direction'    => $interview->direction,
                'level'        => $interview->level,
            ],
        );
    }

    /** @return array<string, int> */
    public function jailbreakStats(): array
    {
        $base = ConductViolationLog::query()
            ->where('category', ConductViolationLog::CATEGORY_JAILBREAK);

        return [
            'today'   => (clone $base)->whereDate('created_at', today())->count(),
            'mentor'  => $this->sourceJailbreakStats(ConductViolationLog::SOURCE_MENTOR_CHAT),
            'hr'      => $this->sourceJailbreakStats(ConductViolationLog::SOURCE_HR_INTERVIEW),
        ];
    }

    /** @return array{today: int, week: int, total: int, blocked: int} */
    private function sourceJailbreakStats(string $source): array
    {
        $base = ConductViolationLog::query()
            ->where('category', ConductViolationLog::CATEGORY_JAILBREAK)
            ->where('source', $source);

        return [
            'today'   => (clone $base)->whereDate('created_at', today())->count(),
            'week'    => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
            'total'   => (clone $base)->count(),
            'blocked' => (clone $base)->whereIn('action', [
                ConductViolationLog::ACTION_BLOCK,
                ConductViolationLog::ACTION_TERMINATE,
            ])->count(),
        ];
    }

    /**
     * @param array{category?: string, confidence?: float, reason?: string} $classification
     * @param array<string, mixed> $context
     */
    private function persist(
        ?int $userId,
        string $source,
        string $category,
        string $action,
        int $warningNumber,
        string $userMessage,
        array $classification,
        array $context,
    ): void {
        $log = ConductViolationLog::create([
            'user_id'                => $userId,
            'source'                 => $source,
            'category'               => $category,
            'action'                 => $action,
            'warning_number'         => $warningNumber,
            'message_excerpt'        => Str::limit(trim($userMessage), 500, '…'),
            'classifier_reason'      => Str::limit(trim((string) ($classification['reason'] ?? '')), 500, '…') ?: null,
            'classifier_confidence'  => isset($classification['confidence'])
                ? round((float) $classification['confidence'], 3)
                : null,
            'context'                => $context,
        ]);

        Log::warning('Conduct violation logged', [
            'log_id'   => $log->id,
            'source'   => $source,
            'category' => $category,
            'action'   => $action,
            'user_id'  => $userId,
        ]);
    }

    private function mapMentorAction(string $conductAction): string
    {
        return match ($conductAction) {
            LessonMentorConductService::ACTION_REFUSE => ConductViolationLog::ACTION_BLOCK,
            LessonMentorConductService::ACTION_WARN   => ConductViolationLog::ACTION_WARN,
            default                                   => ConductViolationLog::ACTION_WARN,
        };
    }

    private function mapHrAction(string $conductAction): string
    {
        return match ($conductAction) {
            HrInterviewConductService::ACTION_TERMINATE => ConductViolationLog::ACTION_TERMINATE,
            HrInterviewConductService::ACTION_WARN      => ConductViolationLog::ACTION_WARN,
            default                                     => ConductViolationLog::ACTION_WARN,
        };
    }
}
