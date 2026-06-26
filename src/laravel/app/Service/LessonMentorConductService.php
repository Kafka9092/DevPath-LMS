<?php

namespace App\Service;

use App\Models\UserProgressSubtopic;

/**
 * Conduct-слой чата ментора: политика предупреждений/блокировки поверх семантического классификатора.
 */
class LessonMentorConductService
{
    public const ACTION_CONTINUE = 'continue';

    public const ACTION_WARN = 'warn';

    public const ACTION_REFUSE = 'refuse';

    public const ACTION_REDIRECT = 'redirect';

    public const BLOCK_MESSAGE = 'Вы нарушили правила общения с ментором. На этом уроке ментор не доступен.';

    public function __construct(
        protected LessonMentorConductClassifierService $classifier,
        protected ConductViolationLogger $violationLogger,
    ) {}

    public function handleUserMessage(
        UserProgressSubtopic $progress,
        string $subtopicTitle,
        string $userMessage,
    ): array {
        if ($this->isChatBlocked($progress)) {
            return $this->blockedResponse();
        }

        $classification = $this->classifier->classify($subtopicTitle, $userMessage);

        return match ($classification['category']) {
            ConductIntentClassifier::CATEGORY_JAILBREAK => $this->handleMisconduct(
                $progress,
                $subtopicTitle,
                $userMessage,
                $classification,
                'jailbreak',
            ),
            ConductIntentClassifier::CATEGORY_ABUSE => $this->handleMisconduct(
                $progress,
                $subtopicTitle,
                $userMessage,
                $classification,
                'misconduct',
            ),
            ConductIntentClassifier::CATEGORY_OFF_TOPIC => [
                'action'  => self::ACTION_REDIRECT,
                'message' => 'Давайте вернёмся к уроку «'
                    . $subtopicTitle
                    . '». Задайте вопрос по теории или практике — я помогу разобраться по шагам.',
            ],
            default => ['action' => self::ACTION_CONTINUE],
        };
    }

    public function isChatBlocked(UserProgressSubtopic $progress): bool
    {
        return (bool) $progress->mentor_chat_blocked;
    }

    public function blockedResponse(): array
    {
        return [
            'action'              => self::ACTION_REFUSE,
            'message'             => self::BLOCK_MESSAGE,
            'mentor_chat_blocked' => true,
        ];
    }

    public function breaksMentorRole(string $aiResponse): bool
    {
        $lower = mb_strtolower($aiResponse);

        foreach ($this->mentorRoleBreakPatterns() as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function inRoleFallbackMessage(string $subtopicTitle): string
    {
        return 'Продолжим урок «' . $subtopicTitle . '». Сформулируйте вопрос по текущей теме — разберём логику и типичные ошибки.';
    }
    private function handleMisconduct(
        UserProgressSubtopic $progress,
        string $subtopicTitle,
        string $userMessage,
        array $classification,
        string $reason,
    ): array {
        $warnings = (int) $progress->mentor_conduct_warnings + 1;

        if ($warnings >= 2) {
            $progress->update([
                'mentor_conduct_warnings' => $warnings,
                'mentor_chat_blocked'     => true,
            ]);

            $result = [
                'action'              => self::ACTION_REFUSE,
                'message'             => self::BLOCK_MESSAGE,
                'reason'              => $reason . '_repeat',
                'mentor_chat_blocked' => true,
            ];
        } else {
            $progress->update(['mentor_conduct_warnings' => $warnings]);

            $message = $reason === 'jailbreak'
                ? 'Я ментор по уроку и не меняю правила или роль. Задайте вопрос по текущей теме — помогу с теорией или практикой.'
                : 'Прошу придерживаться делового тона. Продолжим урок — задайте вопрос по материалу.';

            $result = [
                'action'              => self::ACTION_WARN,
                'message'             => $message,
                'mentor_chat_blocked' => false,
            ];
        }

        $this->violationLogger->logMentorViolation(
            $progress,
            $subtopicTitle,
            $userMessage,
            $classification,
            $reason,
            $result['action'],
            $warnings,
        );

        return $result;
    }

    /** @return list<string> */
    private function mentorRoleBreakPatterns(): array
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
