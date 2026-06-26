<?php

namespace App\Service;

use App\Models\Subtopic;
use App\Models\UserLearningProfile;
use App\Models\UserProgressSubtopic;
use Illuminate\Support\Facades\Log;

class MentorHelpService
{
    private const HELP_COOLDOWN_MINUTES = 20;

    public function __construct(
        protected OllamaService $ollama,
        protected LessonContextBuilder $contextBuilder,
        protected CodeAnalysisHelper $codeAnalysis,
    ) {}

    /**
     * @return list<array{id: string, label: string, icon: string}>
     */
    public function buildMenuOptions(bool $hasMeaningfulCode): array
    {
        if (!$hasMeaningfulCode) {
            return [
                ['id' => 'concept', 'label' => 'Напомнить концепцию / алгоритм', 'icon' => 'map'],
                ['id' => 'architecture', 'label' => 'Показать пример похожей архитектуры', 'icon' => 'squares'],
                ['id' => 'first_steps', 'label' => 'Предложить первый шаг (план действий)', 'icon' => 'rocket'],
            ];
        }

        return [
            ['id' => 'concept', 'label' => 'Напомнить концепцию / алгоритм', 'icon' => 'map'],
            ['id' => 'review_code', 'label' => 'Проверить мой текущий код на ошибки', 'icon' => 'magnifier'],
            ['id' => 'architecture', 'label' => 'Показать пример похожей архитектуры', 'icon' => 'squares'],
        ];
    }

    public function promptBlock(): array
    {
        return [
            'type'    => 'mentor_prompt',
            'message' => 'Привет! Вижу, задача заставила задуматься. Нужна небольшая подсказка, чтобы сдвинуться с места?',
            'actions' => [
                ['id' => 'accept', 'label' => 'Да, давай'],
                ['id' => 'decline', 'label' => 'Нет, я сам'],
            ],
        ];
    }

    public function menuBlock(bool $hasMeaningfulCode): array
    {
        return [
            'type'         => 'mentor_menu',
            'message'      => 'Выберите, чем помочь — я подстроюсь под то, что уже есть в редакторе.',
            'menu_options' => $this->buildMenuOptions($hasMeaningfulCode),
        ];
    }

    public function shouldOfferStallHelp(UserLearningProfile $profile): bool
    {
        if (!$profile->last_help_offered_at) {
            return true;
        }

        return $profile->last_help_offered_at->lt(now()->subMinutes(self::HELP_COOLDOWN_MINUTES));
    }

    public function markHelpOffered(UserLearningProfile $profile): void
    {
        $profile->update(['last_help_offered_at' => now()]);
    }

    public function generateOptionHelp(
        Subtopic $subtopic,
        UserProgressSubtopic $progress,
        ?UserLearningProfile $profile,
        int $courseId,
        int $userId,
        string $optionId,
        ?string $currentCode,
        ?string $starterCode = null,
    ): array {
        $course = $subtopic->theme->module->course;
        $ctx    = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);
        $task   = $progress->task_description ?? $subtopic->task ?? $subtopic->title;
        $lang   = $ctx['direction'] ?? 'код';
        $hasCode = $this->codeAnalysis->hasMeaningfulCode($currentCode, $starterCode);
        $codeSection = $this->editorSection($currentCode, $lang, $hasCode);

        $focus = match ($optionId) {
            'concept'      => 'Напомни концепцию и алгоритм решения без кода. С чего начать проектирование.',
            'architecture' => 'Покажи пример похожей архитектуры или структуры модулей — только ориентир, не полное решение задачи.',
            'first_steps'  => 'Разбей задачу на 3 простых шага «сделай раз — сделай два» на словах, без готового кода.',
            'review_code'  => 'Проверь текущий код студента: укажи логические ошибки и пробелы. Без готового решения.',
            default        => 'Дай краткую наводящую подсказку.',
        };

        if ($optionId === 'review_code' && !$hasCode) {
            return [
                'type'    => 'answer',
                'title'   => 'Подсказка',
                'content' => 'В редакторе пока мало кода для разбора. Начните с первого шага — могу предложить план действий.',
            ];
        }

        $rules = $this->contextBuilder->mentorDialogRules();

        $prompt = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$rules}
Подтема: «{$subtopic->title}»
Задача: {$task}
{$codeSection}

Запрос студента: {$focus}

Верни JSON: {"title": "краткий заголовок", "content": "ответ в markdown-lite (без emoji)"}
PROMPT;

        if (!$this->ollama->isAvailable()) {
            return $this->fallbackHelp($optionId);
        }

        try {
            $data = $this->ollama->generateJson($prompt);

            return [
                'type'      => 'hint',
                'hint_type' => $optionId,
                'title'     => $data['title'] ?? 'Подсказка',
                'content'   => $data['content'] ?? 'Попробуйте разбить задачу на более мелкие шаги.',
            ];
        } catch (\Exception $e) {
            Log::error('Mentor option help failed: ' . $e->getMessage());

            return $this->fallbackHelp($optionId);
        }
    }

    private function editorSection(?string $code, string $lang, bool $hasCode): string
    {
        if (!$hasCode || trim($code ?? '') === '') {
            return 'Код в редакторе: только шаблон или пусто.';
        }

        $trimmed = trim($code);

        return "Текущий код студента ({$lang}):\n```\n{$trimmed}\n```";
    }

    private function fallbackHelp(string $optionId): array
    {
        $content = match ($optionId) {
            'first_steps' => "1. Выделите входные данные и ожидаемый результат.\n2. Опишите промежуточные шаги на бумаге.\n3. Реализуйте один шаг и проверьте его отдельно.",
            'review_code' => 'Сначала добавьте в редактор черновик логики — тогда смогу указать на конкретные места.',
            default       => 'Попробуйте разбить задачу на 2–3 подзадачи и решать по одной.',
        };

        return [
            'type'    => 'hint',
            'title'   => 'Подсказка',
            'content' => $content,
        ];
    }
}
