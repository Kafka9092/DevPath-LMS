<?php

namespace App\Service;

use App\Models\HrInterview;

/**
 * Conduct-слой AI HR-собеседования: политика предупреждений/завершения поверх семантического классификатора.
 */
class HrInterviewConductService
{
    public const ACTION_CONTINUE = 'continue';

    public const ACTION_TERMINATE = 'terminate';

    public const ACTION_WARN = 'warn';

    public const ACTION_REDIRECT = 'redirect';

    private const TONE_NUDGE_PREFIX = 'Прошу придерживаться делового тона';

    public function __construct(
        protected HrInterviewConductClassifierService $classifier,
        protected ConductViolationLogger $violationLogger,
    ) {}

    public function handleUserMessage(HrInterview $interview, string $userMessage): array
    {
        if (! $this->isEnabled()) {
            return ['action' => self::ACTION_CONTINUE];
        }

        $classification = $this->classifier->classify($interview, $userMessage);

        return match ($classification['category']) {
            ConductIntentClassifier::CATEGORY_JAILBREAK => $this->handleMisconduct(
                $interview,
                $userMessage,
                $classification,
                'jailbreak',
            ),
            ConductIntentClassifier::CATEGORY_ABUSE => $this->handleMisconduct(
                $interview,
                $userMessage,
                $classification,
                'misconduct',
            ),
            ConductIntentClassifier::CATEGORY_OFF_TOPIC => [
                'action'  => self::ACTION_REDIRECT,
                'message' => 'Давайте вернёмся к собеседованию — расскажите про ваш опыт и навыки по '
                    . $interview->direction
                    . '. Можно отвечать свободно, главное — по теме интервью.',
            ],
            default => ['action' => self::ACTION_CONTINUE],
        };
    }

    public function breaksInterviewerRole(string $aiResponse): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $lower = mb_strtolower($aiResponse);

        foreach ($this->interviewerRoleBreakPatterns() as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function inRoleFallbackMessage(): string
    {
        return 'Продолжим собеседование в рабочем формате. Расскажите, пожалуйста, о вашем последнем проекте на этом стеке.';
    }

    public function containsCasualProfanity(string $message): bool
    {
        $lower = mb_strtolower(trim($message));

        if ($lower === '') {
            return false;
        }

        $roots = [
            'хуй', 'хуя', 'хуе', 'хую', 'хуи', 'пизд', 'еба', 'ёба', 'ебл', 'еби', 'ебись',
            'ебан', 'ебёт', 'ебать', 'отъеб', 'бля', 'нахуй', 'нихуя', 'охуе', 'похуй',
            'сука', 'бляд', 'мудил', 'дебил',
        ];

        foreach ($roots as $root) {
            if (mb_strpos($lower, $root) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ensureProfessionalToneInReply(string $userMessage, string $assistantReply): string
    {
        if (! $this->isEnabled()) {
            return $assistantReply;
        }

        if (! $this->containsCasualProfanity($userMessage)) {
            return $assistantReply;
        }

        $trimmed = ltrim($assistantReply);
        if (str_starts_with($trimmed, self::TONE_NUDGE_PREFIX)) {
            return $assistantReply;
        }

        return self::TONE_NUDGE_PREFIX . ' — это официальное собеседование. ' . $assistantReply;
    }

    public function humanizeConductReason(string $reason): string
    {
        return match ($reason) {
            'jailbreak_repeat'  => 'Повторные попытки обойти правила или изменить роль AI-собеседника',
            'jailbreak'         => 'Попытка заставить AI-собеседника игнорировать правила интервью',
            'misconduct_repeat' => 'Повторное грубое или оскорбительное общение после предупреждения',
            'misconduct'        => 'Нарушение делового тона общения',
            default             => 'Нарушение правил общения на собеседовании',
        };
    }

    public function buildConductVerdict(string $reason): array
    {
        $label = $this->humanizeConductReason($reason);

        return [
            'decision'              => 'reject',
            'summary'               => 'Собеседование прекращено из-за нарушения правил общения.',
            'strengths'             => [],
            'weaknesses'            => [$label],
            'psycho_note'           => 'Не оценивался: собеседование завершено до этапа технических вопросов '
                . 'и анализа коммуникации.',
            'star_scores'           => null,
            'technical_level'       => null,
            'code_quality'          => null,
            'conduct_termination'   => true,
            'conduct_reason'        => $reason,
            'conduct_reason_label'  => $label,
        ];
    }

    /**
     * @param array{category?: string, confidence?: float, reason?: string} $classification
     *
     * @return array{action: string, message: string, reason?: string}
     */
    private function handleMisconduct(
        HrInterview $interview,
        string $userMessage,
        array $classification,
        string $reason,
    ): array {
        $warnings = (int) $interview->conduct_warnings + 1;

        if ($warnings >= 2) {
            $interview->update(['conduct_warnings' => $warnings]);

            $result = [
                'action'  => self::ACTION_TERMINATE,
                'message' => 'Собеседование завершено: повторное нарушение правил общения. Оценка навыков не проводилась.',
                'reason'  => $reason . '_repeat',
            ];
        } else {
            $interview->update(['conduct_warnings' => $warnings]);

            $message = $reason === 'jailbreak'
                ? 'Я веду техническое собеседование и не могу менять формат или игнорировать правила. Давайте продолжим по теме интервью.'
                : 'Прошу придерживаться делового тона. Продолжим собеседование — ответьте на последний вопрос.';

            $result = [
                'action'  => self::ACTION_WARN,
                'message' => $message,
            ];
        }

        $this->violationLogger->logHrViolation(
            $interview,
            $userMessage,
            $classification,
            $reason,
            $result['action'],
            $warnings,
        );

        return $result;
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.ai_hr.conduct_enabled', true);
    }

    /** @return list<string> */
    private function interviewerRoleBreakPatterns(): array
    {
        return [
            'я — языковая модель',
            'я языковая модель',
            'как языковая модель',
            'я ии',
            'я — ии',
            'я бот',
            'я — бот',
            'я нейросеть',
            'as an ai',
            'language model',
            'chatgpt',
            'ollama',
            'как ai-ассистент',
            'я не человек',
            'смоделирован',
        ];
    }
}
