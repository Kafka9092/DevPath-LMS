<?php

namespace App\Service;

use Illuminate\Support\Facades\Log;

/**
 * Базовый семантический классификатор намерений для conduct-слоёв (ментор, HR).
 */
abstract class ConductIntentClassifier
{
    public const CATEGORY_ON_TOPIC = 'on_topic';

    public const CATEGORY_JAILBREAK = 'jailbreak';

    public const CATEGORY_ABUSE = 'abuse';

    public const CATEGORY_OFF_TOPIC = 'off_topic';

    private const CONFIDENCE_THRESHOLD = 0.65;

    public function __construct(
        protected OllamaService $ollama,
    ) {}

    /**
     * @return array{category: string, confidence: float, reason: string}
     */
    protected function classifyMessage(string $userMessage): array
    {
        $message = trim($userMessage);

        if ($message === '') {
            return $this->onTopic('пустое сообщение');
        }

        if ($fast = $this->tryFastClassify($message)) {
            return $this->normalize($fast);
        }

        if (! $this->ollama->isAvailable()) {
            Log::warning($this->logLabel() . ': Ollama unavailable');

            return $this->onTopic('ollama unavailable');
        }

        try {
            $raw = $this->ollama->generateJson(
                $this->buildPrompt($message),
                null,
                ['temperature' => 0.0, 'num_predict' => 256],
                15,
            );

            return $this->normalize($raw);
        } catch (\Throwable $e) {
            Log::warning($this->logLabel() . ' failed: ' . $e->getMessage());

            return $this->onTopic('classifier error');
        }
    }

    /**
     * Локальная классификация без Ollama — для явных jailbreak/abuse, чтобы не блокировать HTTP-запрос.
     *
     * @return array{category: string, confidence: float, reason: string}|null
     */
    protected function tryFastClassify(string $userMessage): ?array
    {
        $lower = mb_strtolower(trim($userMessage));

        if ($lower === '') {
            return null;
        }

        foreach ($this->jailbreakPhrases() as $phrase) {
            if (str_contains($lower, $phrase)) {
                return [
                    'category'   => self::CATEGORY_JAILBREAK,
                    'confidence' => 0.96,
                    'reason'     => 'явная попытка обойти правила',
                ];
            }
        }

        if ($this->isDismissiveAbuse($lower)) {
            return [
                'category'   => self::CATEGORY_ABUSE,
                'confidence' => 0.96,
                'reason'     => 'грубое или оскорбительное обращение',
            ];
        }

        return null;
    }

    /** @return list<string> */
    protected function profanityRoots(): array
    {
        return [
            'хуй', 'хуя', 'хуе', 'хую', 'хуи', 'пизд', 'еба', 'ёба', 'ебл', 'еби', 'ебись',
            'ебан', 'ебёт', 'ебать', 'отъеб', 'бля', 'нахуй', 'нихуя', 'охуе', 'похуй',
            'сука', 'бляд', 'мудил', 'дебил',
        ];
    }

    protected function containsProfanity(string $message): bool
    {
        $lower = mb_strtolower(trim($message));

        foreach ($this->profanityRoots() as $root) {
            if (mb_strpos($lower, $root) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    protected function dismissiveAbusePhrases(): array
    {
        return [
            'отъебись', 'отъебитесь', 'отвали', 'отстань', 'заткнись', 'съебись',
            'иди нах', 'иди на х', 'пошёл нах', 'пошел нах', 'нахуй ид', 'на хуй',
            'нахуй те', 'пошла нах', 'пошёл на', 'пошел на', 'иди в жоп', 'иди в зад',
        ];
    }

    protected function isDismissiveAbuse(string $lower): bool
    {
        foreach ($this->dismissiveAbusePhrases() as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        if (! $this->containsProfanity($lower)) {
            return false;
        }

        return mb_strlen($lower) <= 50;
    }

    /** @return list<string> */
    protected function jailbreakPhrases(): array
    {
        return [
            'забудь все инструк',
            'забудь инструк',
            'игнорируй систем',
            'игнорируй инструк',
            'system prompt',
            'без ограничений',
            'отвечай без огранич',
            'режим dan',
            'jailbreak',
            'раскрой системный промпт',
            'покажи системный промпт',
        ];
    }

    abstract protected function buildPrompt(string $userMessage): string;

    abstract protected function logLabel(): string;

    /** @return array{category: string, confidence: float, reason: string} */
    protected function normalize(array $raw): array
    {
        $category = mb_strtolower(trim((string) ($raw['category'] ?? '')));
        $confidence = max(0.0, min(1.0, (float) ($raw['confidence'] ?? 0)));
        $reason = trim((string) ($raw['reason'] ?? ''));

        $allowed = [
            self::CATEGORY_ON_TOPIC,
            self::CATEGORY_JAILBREAK,
            self::CATEGORY_ABUSE,
            self::CATEGORY_OFF_TOPIC,
        ];

        if (! in_array($category, $allowed, true)) {
            return $this->onTopic('invalid category from model');
        }

        if ($category === self::CATEGORY_ON_TOPIC) {
            return [
                'category'   => self::CATEGORY_ON_TOPIC,
                'confidence' => $confidence,
                'reason'     => $reason,
            ];
        }

        if ($confidence < self::CONFIDENCE_THRESHOLD) {
            return $this->onTopic('low confidence: ' . $reason);
        }

        return [
            'category'   => $category,
            'confidence' => $confidence,
            'reason'     => $reason,
        ];
    }

    /** @return array{category: string, confidence: float, reason: string} */
    protected function onTopic(string $reason): array
    {
        return [
            'category'   => self::CATEGORY_ON_TOPIC,
            'confidence' => 1.0,
            'reason'     => $reason,
        ];
    }

    protected function escapeForPrompt(string $text): string
    {
        return str_replace(['"""', '\\'], ['\\"\\"\\"', '\\\\'], $text);
    }
}
