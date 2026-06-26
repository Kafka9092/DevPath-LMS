<?php

namespace App\Service;

use App\Models\CodeReview;
use App\Service\Kafka\CodeReviewKafkaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Code Review — SonarQube + Ollama.
 * API отдаёт job_id, тяжёлую работу делает kafka:code-review-consume.
 */
class CodeReviewService
{
    private const EXTENSIONS = [
        'php'          => 'php',
        'javascript'   => 'js',
        'typescript'   => 'ts',
        'python'       => 'py',
        'java'         => 'java',
        'csharp'       => 'cs',
        'c#'           => 'cs',
        'go'           => 'go',
        'ruby'         => 'rb',
        'rust'         => 'rs',
        'kotlin'       => 'kt',
        'swift'        => 'swift',
        'cpp'          => 'cpp',
        'c++'          => 'cpp',
        'c'            => 'c',
    ];

    private const MONACO_LANGUAGES = [
        'php'        => 'php',
        'javascript' => 'javascript',
        'typescript' => 'typescript',
        'python'     => 'python',
        'java'       => 'java',
        'csharp'     => 'csharp',
        'go'         => 'go',
        'ruby'       => 'ruby',
        'rust'       => 'rust',
        'kotlin'     => 'kotlin',
        'swift'      => 'swift',
        'cpp'        => 'cpp',
        'c'          => 'c',
    ];

    public function __construct(
        protected SonarQubeService $sonar,
        protected OllamaService $ollama,
        protected CodeReviewKafkaService $kafka,
    ) {}

    /** Точка входа с фронта — либо Kafka, либо сразу reviewSync. */
    public function review(string $code, ?int $userId = null, array $context = []): array
    {
        if (! $this->looksLikeCode($code)) {
            return $this->buildNotCodeResponse();
        }

        if ($this->kafka->useKafkaFor('analyze')) {
            $jobId = $this->kafka->publish(
                $this->kafka->topic('analyze'),
                [
                    'code'    => $code,
                    'user_id' => $userId,
                    'context' => $context,
                    'persist' => true,
                ],
                $userId ? (string) $userId : null,
            );

            return [
                'status'  => 'generating_analyze',
                'job_id'  => $jobId,
                'success' => true,
            ];
        }

        return $this->reviewSync($code, $userId, $context, persist: true);
    }

    /**
     * Проверка без записи в БД — для VS Code и других клиентов «на лету».
     * Всегда синхронно, без Kafka.
     *
     * @param  array{language?: string|null, filename?: string|null, file_path?: string|null}  $context
     */
    public function reviewPreview(string $code, array $context = []): array
    {
        if (! $this->looksLikeCode($code)) {
            return $this->buildNotCodeResponse();
        }

        if ($this->kafka->useKafkaFor('analyze')) {
            $jobId = $this->kafka->publish(
                $this->kafka->topic('analyze'),
                [
                    'code'    => $code,
                    'user_id' => null,
                    'context' => $context,
                    'persist' => false,
                ],
            );

            return [
                'status'  => 'generating_analyze',
                'job_id'  => $jobId,
                'success' => true,
            ];
        }

        return $this->reviewSync($code, null, $context, persist: false);
    }

    /**
     * Реальная проверка: язык → Sonar → Ollama → опционально запись в code_reviews.
     * Этот метод дергает consumer когда Kafka включён.
     */
    public function reviewSync(string $code, ?int $userId = null, array $context = [], bool $persist = true): array
    {
        $language = $this->resolveLanguage(
            $code,
            $context['language'] ?? null,
            $context['filename'] ?? null,
        );

        if ($language === 'unknown' || ! $this->looksLikeCode($code)) {
            return $this->buildNotCodeResponse();
        }

        $extension  = self::EXTENSIONS[strtolower($language)] ?? 'txt';
        $projectKey = $this->sonar->saveCodeSnippet($code, null, $extension);
        $sonarResult = $this->sonar->analyzeProject($projectKey, $extension);
        $aiEvaluation = $this->normalizeAiEvaluation($this->buildAiEvaluation($code, $language, $sonarResult));

        $uuid = (string) Str::uuid();
        $overallScore = (int) ($aiEvaluation['overall_score'] ?? $aiEvaluation['score'] ?? 0);

        if ($persist) {
            $record = CodeReview::create([
                'uuid'               => $uuid,
                'user_id'            => $userId,
                'detected_language'  => $language,
                'code'               => $code,
                'sonar_project_key'  => $projectKey,
                'sonar_metrics'      => $sonarResult['metrics'] ?? [],
                'sonar_issues'       => $sonarResult['issues'] ?? [],
                'ai_evaluation'      => $aiEvaluation,
                'overall_score'      => $overallScore,
            ]);

            $uuid = $record->uuid;
        }

        return [
            'uuid'               => $uuid,
            'is_code'            => true,
            'detected_language'  => $language,
            'editor_language'    => self::MONACO_LANGUAGES[strtolower($language)] ?? 'plaintext',
            'project_key'        => $projectKey,
            'metrics'            => $sonarResult['metrics'] ?? [],
            'issues'             => $this->sortIssuesByLine($sonarResult['issues'] ?? []),
            'ai_evaluation'      => $aiEvaluation,
            'overall_score'      => $overallScore,
            'saved'              => $persist && $userId !== null,
        ];
    }

    private function buildNotCodeResponse(): array
    {
        $messages = [
            'Похоже, вы написали обычный текст, а не программу. SonarQube умеет искать баги в коде, но не рецензировать переписку.',
            'Это не код — это, скорее, мысли вслух. Вставьте фрагмент на PHP, Python, Java или другом языке, и мы разберём его по полочкам.',
            'Мы проверяем исходники, а не SMS коту. Напишите что-нибудь с function, class, def или <?php — и погнали!',
        ];

        $hints = [
            'Попробуйте, например: echo "Hello, World!"; или print("Привет")',
            'Поддерживаются: PHP, JavaScript, TypeScript, Python, Java, C#, Go, Ruby, Rust, Kotlin, Swift, C/C++',
        ];

        return [
            'uuid'              => (string) Str::uuid(),
            'is_code'           => false,
            'detected_language' => 'unknown',
            'editor_language'   => 'plaintext',
            'not_code_message'  => $messages[array_rand($messages)],
            'not_code_hint'     => $hints[array_rand($hints)],
            'saved'             => false,
        ];
    }

    
    private function looksLikeCode(string $code): bool
    {
        $trimmed = trim($code);
        if ($trimmed === '') {
            return false;
        }

        if ($this->looksLikeJsonPayload($trimmed)) {
            return false;
        }

        $strongSignals = 0;
        $patterns = [
            '/<\?php/i',
            '/\b(function|class|def|import|package|public|private|protected|interface|enum)\s+/i',
            '/\b(console\.log|System\.out|fmt\.Print|println!)\b/',
            '/^\s*#include\s+/m',
            '/^\s*using\s+[\w.]+;/m',
            '/=>\s*[({\[]/',
            '/[{}]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                $strongSignals++;
            }
        }

        if ($strongSignals >= 1) {
            return true;
        }

        if (preg_match('/[();]/', $code) && preg_match('/\b(if|for|while|return|var|let|const)\b/i', $code)) {
            return true;
        }

        $lines = preg_split('/\r\n|\r|\n/', $trimmed);
        $lineCount = count(array_filter($lines, fn ($l) => trim($l) !== ''));

        if ($lineCount <= 3
            && ! preg_match('/[{}();=<>]/', $trimmed)
            && preg_match('/^[\p{Cyrillic}\p{Latin}\s\d.,!?«»"\'\-—:]+$/u', $trimmed)
        ) {
            return false;
        }

        return preg_match('/[{}();=]/', $trimmed) === 1;
    }

    /** Чистый JSON без синтаксиса языка — не исходник (частая подделка ответа API). */
    private function looksLikeJsonPayload(string $trimmed): bool
    {
        if (! preg_match('/^\{.*\}$/s', $trimmed) && ! preg_match('/^\[.*\]$/s', $trimmed)) {
            return false;
        }

        json_decode($trimmed);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return ! preg_match('/<\?php|\b(function|class|def|import|package)\b/i', $trimmed);
    }

    public function latestForUser(?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }

        $record = CodeReview::query()
            ->where('user_id', $userId)
            ->latest()
            ->first();

        if (!$record) {
            return null;
        }

        return $this->formatRecord($record);
    }

    public function formatRecord(CodeReview $record): array
    {
        $aiEvaluation = $this->normalizeAiEvaluation($record->ai_evaluation ?? []);

        return [
            'uuid'              => $record->uuid,
            'is_code'           => true,
            'code'              => $record->code,
            'detected_language' => $record->detected_language,
            'editor_language'   => self::MONACO_LANGUAGES[strtolower($record->detected_language)] ?? 'plaintext',
            'project_key'       => $record->sonar_project_key,
            'metrics'           => $record->sonar_metrics ?? [],
            'issues'            => $this->sortIssuesByLine($record->sonar_issues ?? []),
            'ai_evaluation'     => $aiEvaluation,
            'overall_score'     => $record->overall_score,
            'created_at'        => $record->created_at?->toIso8601String(),
        ];
    }

    private function normalizeAiEvaluation(array $aiEvaluation): array
    {
        $aiEvaluation['explained_issues'] = $this->sortIssuesByLine($aiEvaluation['explained_issues'] ?? []);

        return $aiEvaluation;
    }

    private function sortIssuesByLine(array $issues): array
    {
        usort($issues, function (array $a, array $b): int {
            $lineA = isset($a['line']) && $a['line'] !== null ? (int) $a['line'] : PHP_INT_MAX;
            $lineB = isset($b['line']) && $b['line'] !== null ? (int) $b['line'] : PHP_INT_MAX;

            return $lineA <=> $lineB;
        });

        return array_values($issues);
    }

    public function detectLanguage(string $code): string
    {
        return $this->resolveLanguage($code, null);
    }

    private function resolveLanguage(string $code, ?string $hint, ?string $filename = null): string
    {
        if ($hint !== null && $hint !== '') {
            $normalized = $this->normalizeLanguageHint($hint);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        $fromFilename = $this->languageFromFilename($filename);
        if ($fromFilename !== null) {
            return $fromFilename;
        }

        return $this->detectLanguageFromCode($code);
    }

    private function languageFromFilename(?string $filename): ?string
    {
        if ($filename === null || $filename === '') {
            return null;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return null;
        }

        return match ($ext) {
            'php'                 => 'php',
            'js', 'jsx', 'mjs', 'cjs' => 'javascript',
            'ts', 'tsx'           => 'typescript',
            'py'                  => 'python',
            'java'                => 'java',
            'cs'                  => 'csharp',
            'go'                  => 'go',
            'rb'                  => 'ruby',
            'rs'                  => 'rust',
            'kt', 'kts'           => 'kotlin',
            'swift'               => 'swift',
            'cpp', 'cc', 'cxx', 'hpp' => 'cpp',
            'c', 'h'              => 'c',
            default               => null,
        };
    }

    private function normalizeLanguageHint(string $hint): ?string
    {
        $normalized = strtolower(trim($hint));

        $aliases = [
            'js'     => 'javascript',
            'ts'     => 'typescript',
            'py'     => 'python',
            'c#'     => 'csharp',
            'c++'    => 'cpp',
        ];

        if (isset($aliases[$normalized])) {
            $normalized = $aliases[$normalized];
        }

        if (isset(self::EXTENSIONS[$normalized]) || isset(self::MONACO_LANGUAGES[$normalized])) {
            return $normalized;
        }

        return null;
    }

    private function detectLanguageFromCode(string $code): string
    {
        $snippet = mb_substr(trim($code), 0, 4000);

        $prompt = <<<PROMPT
Определи язык программирования по фрагменту.

Если это обычный текст, переписка, стихи, комментарий без кода или фрагмент не на языке программирования — верни "unknown".

Код:
```
{$snippet}
```

Верни ТОЛЬКО JSON:
{"language":"php|javascript|typescript|python|java|csharp|go|ruby|rust|kotlin|swift|cpp|c|unknown"}
PROMPT;

        try {
            $result = $this->ollama->generateJson($prompt);
            $language = strtolower(trim($result['language'] ?? 'unknown'));

            return array_key_exists($language, self::EXTENSIONS) ? $language : 'unknown';
        } catch (\Throwable $e) {
            Log::warning('Code language detection failed: ' . $e->getMessage());

            return $this->guessLanguageHeuristic($code);
        }
    }

    private function guessLanguageHeuristic(string $code): string
    {
        if (str_contains($code, '<?php')
            || (str_contains($code, 'namespace ') && preg_match('/\$[a-zA-Z_]/', $code))
            || preg_match('/\b(class|function|trait|interface|enum)\b/', $code) && preg_match('/[{};]/', $code)
        ) {
            return 'php';
        }
        if (preg_match('/\b(import|def|print\()\b/', $code)) {
            return 'python';
        }
        if (preg_match('/\b(console\.log|const |let |=>)\b/', $code)) {
            return str_contains($code, ': string') || str_contains($code, 'interface ') ? 'typescript' : 'javascript';
        }
        if (preg_match('/\b(public class|System\.out)\b/', $code)) {
            return 'java';
        }
        if (str_contains($code, 'using System') || str_contains($code, 'namespace ')) {
            return 'csharp';
        }
        if (preg_match('/\bpackage main\b|\bfunc main\b/', $code)) {
            return 'go';
        }

        return 'unknown';
    }

    private function buildAiEvaluation(string $code, string $language, array $sonarResult): array
    {
        if (!($sonarResult['success'] ?? true) || !empty($sonarResult['error'])) {
            return $this->fallbackEvaluation($sonarResult['issues'] ?? [], $sonarResult);
        }

        $issues = array_slice($sonarResult['issues'] ?? [], 0, 15);
        $issuesJson = json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $metricsJson = json_encode($sonarResult['metrics'] ?? [], JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Ты — опытный code reviewer и ментор. Проанализируй код и объясни замечания простым русским языком.

Язык кода: {$language}

SonarQube issues (JSON):
{$issuesJson}

SonarQube metrics:
{$metricsJson}

Код:
```
{$code}
```

Верни ТОЛЬКО JSON:
{
  "summary": "краткий итог на русском (2-3 предложения)",
  "overall_score": число 0-100,
  "grade_label": "Отлично|Хорошо|Удовлетворительно|Требует доработки",
  "strengths": ["что сделано хорошо"],
  "improvements": ["что улучшить в первую очередь"],
  "recommendation": "персональная рекомендация по развитию",
  "criteria": {
    "correctness": 0-100,
    "readability": 0-100,
    "code_structure": 0-100,
    "best_practices": 0-100,
    "maintainability": 0-100
  },
  "explained_issues": [
    {
      "line": число или null,
      "type": "VULNERABILITY|BUG|CODE_SMELL",
      "severity": "BLOCKER|CRITICAL|MAJOR|MINOR|INFO",
      "sonar_message": "оригинальное сообщение SonarQube",
      "explanation": "понятное объяснение ошибки для начинающего разработчика",
      "how_to_fix": "конкретный совет как исправить"
    }
  ]
}

Для каждой проблемы из SonarQube добавь понятное explanation и how_to_fix на русском языке.
Также проанализируй код целиком — укажи серьёзные проблемы (eval, SQL injection и т.д.), даже если Sonar их не нашёл.
Все текстовые поля (summary, improvements, recommendation, explanation, how_to_fix) — только на русском.
PROMPT;

        $system = 'Ты опытный code reviewer и ментор. Отвечай ТОЛЬКО валидным JSON без markdown. Все объяснения — на русском языке.';

        try {
            return $this->ollama->generateJsonViaChat($prompt, $system);
        } catch (\Throwable $e) {
            Log::warning('CodeReview AI evaluation failed: ' . $e->getMessage());

            try {
                return $this->ollama->generateJsonViaChat(
                    $this->buildCompactAiPrompt($code, $language, $issues, $sonarResult),
                    $system,
                    ['num_predict' => 4096],
                );
            } catch (\Throwable $retryError) {
                Log::warning('CodeReview compact AI evaluation failed: ' . $retryError->getMessage());

                return $this->fallbackEvaluation($issues, $sonarResult);
            }
        }
    }

    private function buildCompactAiPrompt(string $code, string $language, array $issues, array $sonarResult): string
    {
        $issuesJson  = json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $metricsJson = json_encode($sonarResult['metrics'] ?? [], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Проанализируй код и замечания SonarQube. Язык: {$language}.

Sonar issues:
{$issuesJson}

Sonar metrics:
{$metricsJson}

Код:
```
{$code}
```

Верни ТОЛЬКО JSON с полями: summary, overall_score, grade_label, strengths, improvements, recommendation, criteria, explained_issues.
Для каждой проблемы Sonar — explanation и how_to_fix на русском.
PROMPT;
    }

    private function fallbackEvaluation(array $issues, array $sonarResult): array
    {
        if (!($sonarResult['success'] ?? true) || !empty($sonarResult['error'])) {
            $message = (string) ($sonarResult['error'] ?? 'SonarQube не вернул результаты анализа');

            return [
                'summary'          => 'SonarQube не смог проанализировать код: ' . $message,
                'overall_score'    => 0,
                'grade_label'      => 'Требует доработки',
                'strengths'        => [],
                'improvements'     => ['Проверьте SONAR_TOKEN в src/laravel/.env и перезапустите контейнеры kafka-code-review-consumer и sonarqube'],
                'recommendation'   => 'Убедитесь, что SonarQube доступен на http://localhost:9000, токен актуален, и воркер имеет доступ к Docker socket.',
                'criteria'         => [
                    'correctness'     => 0,
                    'readability'     => 0,
                    'code_structure'  => 0,
                    'best_practices'  => 0,
                    'maintainability' => 0,
                ],
                'explained_issues' => [],
                'sonar_error'      => $message,
                'sonar_metrics'    => $sonarResult['metrics'] ?? [],
            ];
        }

        $blocking = $this->sonar->hasBlockingIssues($issues);
        $score = $blocking ? 45 : (empty($issues) ? 85 : 65);

        $explained = array_map(fn ($issue) => [
            'line'          => $issue['line'] ?? null,
            'type'          => $issue['type'] ?? 'CODE_SMELL',
            'severity'      => $issue['severity'] ?? 'MAJOR',
            'sonar_message' => $issue['message'] ?? '',
            'explanation'   => $issue['message'] ?? 'Обнаружено замечание SonarQube.',
            'how_to_fix'    => 'Исправьте указанную проблему и перепроверьте код.',
        ], $issues);

        return [
            'summary'         => $blocking
                ? 'Код содержит серьёзные замечания SonarQube и требует доработки.'
                : (empty($issues) ? 'Критичных замечаний SonarQube не найдено.' : 'Есть замечания SonarQube, которые стоит исправить.'),
            'overall_score'   => $score,
            'grade_label'     => $score >= 80 ? 'Хорошо' : ($score >= 60 ? 'Удовлетворительно' : 'Требует доработки'),
            'strengths'       => empty($issues) ? ['Код прошёл базовую проверку без критичных ошибок'] : [],
            'improvements'    => array_column(array_slice($issues, 0, 5), 'message'),
            'recommendation'  => 'Проверьте замечания и улучшите читаемость и структуру кода.',
            'criteria'        => [
                'correctness'      => $score,
                'readability'      => max(40, $score - 5),
                'code_structure'   => max(40, $score - 10),
                'best_practices'   => max(35, $score - 15),
                'maintainability'  => max(40, $score - 8),
            ],
            'explained_issues' => $explained,
            'sonar_metrics'    => $sonarResult['metrics'] ?? [],
        ];
    }
}
