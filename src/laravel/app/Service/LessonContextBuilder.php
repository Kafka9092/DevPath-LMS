<?php

namespace App\Service;

use App\Models\Course;
use App\Models\UserLearningProfile;

class LessonContextBuilder
{
    private const DOMAIN_LABELS = [
        'ecommerce'  => 'E-commerce (каталог, заказы, корзина)',
        'fintech'    => 'FinTech (платежи, транзакции, баланс)',
        'saas'       => 'SaaS / CRM (подписки, клиенты, отчёты)',
        'highload'   => 'Highload Web (пагинация, batch, потоки данных, API под нагрузкой)',
        'ai_data'    => 'AI & Data (датасеты, пайплайны, метрики)',
        'web'        => 'Web Backend (REST API, сервисы)',
        'automation' => 'Automation (боты, парсеры, ETL)',
        'frontend'   => 'Frontend (UI, формы, состояние приложения)',
        'fullstack'  => 'Fullstack (API + клиент)',
        'realtime'   => 'Realtime (WebSocket, live-обновления)',
        'enterprise' => 'Enterprise (корпоративные сервисы)',
        'android'    => 'Android (мобильное приложение)',
        'gamedev'    => 'GameDev (игровая логика, HP, урон, сущности)',
        'embedded'   => 'Embedded / IoT (устройства, сенсоры)',
        'systems'    => 'Systems (низкоуровневые структуры)',
        'desktop'    => 'Desktop (настольное приложение)',
        'devops'     => 'DevOps (инфраструктура, деплой)',
        'general'    => 'прикладная разработка',
    ];

    /** Сущности и сюжеты, которые ИИ должен использовать в выбранном домене. */
    private const DOMAIN_SCENARIOS = [
        'ecommerce'  => 'корзина, заказ, SKU, скидка, доставка, остаток на складе',
        'fintech'    => 'баланс, транзакция, перевод, комиссия, лимит, валюта',
        'saas'       => 'подписка, тариф, клиент CRM, сделка, отчёт, SLA',
        'highload'   => 'offset/limit, batch, cursor, компактный формат числа, preview строки, merge/dedup, rate window',
        'ai_data'    => 'датасет, батч, метрика, пайплайн, feature, score',
        'web'        => 'HTTP-запрос, endpoint, JSON, query-параметр, статус-код',
        'automation' => 'бот, парсер, cron, ETL, webhook, очередь задач',
        'frontend'   => 'форма, поле ввода, валидация UI, состояние компонента',
        'fullstack'  => 'REST API + клиент, DTO, сериализация ответа',
        'realtime'   => 'live-обновление, WebSocket, online-статус, push',
        'enterprise' => 'сотрудник, отдел, заявка, согласование, audit log',
        'android'    => 'экран, intent, push, локальное хранилище',
        'gamedev'    => 'HP, урон, инвентарь, уровень, радиус атаки, cooldown',
        'embedded'   => 'сенсор, показание, порог, устройство, прошивка',
        'systems'    => 'буфер, указатель, аллокация, структура данных',
        'desktop'    => 'окно, файл, настройка, локальный кэш',
        'devops'     => 'деплой, контейнер, healthcheck, конфиг, pipeline',
        'general'    => 'прикладной backend-сервис, пользователь, запись',
    ];

    /** Запрещённые сюжеты, если домен другой. */
    private const DOMAIN_FORBIDDEN = [
        'highload'   => 'RPG, Unity, HP, урон, персонаж, GameDev, игров, баланс счёта, транзакция, нейросет, ML-модел',
        'gamedev'    => 'соцсеть, лента, лайк, FinTech, баланс, перевод, корзина, CRM',
        'fintech'    => 'RPG, HP, урон, Unity, лента, лайк, пост',
        'ecommerce'  => 'RPG, HP, урон, Unity, чат, WebSocket',
        'general'    => 'RPG, Unity, HP, урон — если домен не gamedev',
    ];

    /** Слова, которых не должно быть в title/description/action (реклама домена). */
    private const DOMAIN_LOUD_WORDS = [
        'highload'   => ['соцсет', 'лайк', 'like', 'подписчик', 'instagram', 'facebook', 'tiktok', 'медиа-платформ', 'нейросет', 'репост', 'сторис'],
        'fintech'    => ['финтех', 'fintech', 'банковск', 'криптобирж'],
        'ecommerce'  => ['маркетплейс', 'wildberries', 'ozon', 'amazon'],
        'saas'       => ['saas', 'crm-систем'],
        'gamedev'    => [],
        'general'    => [],
    ];

    /** Сюжеты, запрещённые для языка независимо от домена. */
    private const DIRECTION_FORBIDDEN = [
        'php'        => 'RPG, Unity, GameDev, HP, урон, персонаж, инвентарь, mana, quest, игров',
        'python'     => 'Unity, Unreal, HP, урон, персонаж',
        'javascript' => 'Unity, HP, урон, персонаж',
        'typescript' => 'Unity, HP, урон, персонаж',
        'java'       => 'Unity, HP, урон, персонаж',
        'ruby'       => 'Unity, HP, урон, персонаж',
        'go'         => 'Unity, HP, урон, персонаж',
    ];

    public function __construct(
        protected CatAssessmentService $catAssessments,
        protected UserLearningProfileService $profileService,
        protected SubtopicSkillService $subtopicSkill,
        protected LanguageCompetencePromptBuilder $languagePrompts,
    ) {}

    public function forCourse(int $userId, Course $course, ?string $subtopicTitle = null): array
    {
        $profile = $this->profileService->getProfile($userId, $course->id);
        $cat     = $this->catAssessments->getBaselineForCourse($userId, $course->id);

        $weakTopics   = [];
        $strongTopics = [];
        $competenceScores = [];
        $catAllWeakOrZero = false;

        if ($cat) {
            $weakTopics   = $cat['weak_topics'] ?? [];
            $strongTopics = $cat['strong_topics'] ?? [];
            foreach ($cat['competences'] ?? [] as $row) {
                $competenceScores[] = $row;
                if (!($row['mastered'] ?? false)) {
                    $weakTopics[] = $row['name'];
                } else {
                    $strongTopics[] = $row['name'];
                }
            }
            $weakTopics   = array_values(array_unique(array_filter($weakTopics)));
            $strongTopics = array_values(array_unique(array_filter($strongTopics)));

            if ($competenceScores !== []) {
                $catAllWeakOrZero = collect($competenceScores)->every(
                    fn ($r) => ((int) ($r['score'] ?? 0)) < 40
                );
            }
        }

        $skill = $this->subtopicSkill->resolve($subtopicTitle, $cat);

        $ctx = [
            'has_cat'                => $cat !== null,
            'cat_all_weak_or_zero'   => $catAllWeakOrZero,
            'competence_scores'      => $competenceScores,
            'course_title'           => $course->title,
            'course_slug'            => $course->slug,
            'course_level'           => $course->level->name ?? 'junior',
            'direction'              => $course->direction->name ?? 'PHP',
            'overall_level'          => $cat['overall_level'] ?? ($course->level->name ?? 'junior'),
            'weak_topics'            => $weakTopics,
            'strong_topics'          => $strongTopics,
            'cat_recommendation'     => $cat['recommendation'] ?? null,
            'learning_preference'    => $profile?->learning_preference ?? 'balanced',
            'mentor_persona'         => $profile?->mentor_persona ?? 'colleague',
            'domain_interest'        => $profile?->domain_interest ?? 'general',
            'subtopic_skill'         => $skill['band'],
            'subtopic_skill_score'   => $skill['avg_score'],
            'subtopic_skill_matched' => $skill['matched'],
            'subtopic_title'         => $subtopicTitle,
        ];

        $ctx['theory_parts']     = $this->theoryPartsTarget($ctx);
        $ctx['theory_depth']     = $this->resolveTheoryExplanationLevel($ctx);
        $ctx['task_difficulty']  = $this->resolveTaskDifficulty($ctx);
        $ctx['tasks_required']   = $this->resolveTasksRequired($ctx);
        $ctx['personalization_label'] = $this->personalizationLabel($ctx);

        return $ctx;
    }

    /**
     * Объём теории — только формат обучения из онбординга.
     */
    public function theoryPartsTarget(array $ctx): int
    {
        return match ($ctx['learning_preference'] ?? 'balanced') {
            'practice_heavy' => 2,
            'theory_heavy'   => 5,
            default          => 3,
        };
    }

   
    public function resolveTheoryExplanationLevel(array $ctx): string
    {
        if ($ctx['cat_all_weak_or_zero'] ?? false) {
            return 'beginner';
        }

        if (!($ctx['has_cat'] ?? false)) {
            return 'introductory';
        }

        return match ($ctx['subtopic_skill'] ?? 'neutral') {
            'strong' => 'advanced',
            'weak'   => 'beginner',
            default  => 'standard',
        };
    }

    public function resolveTaskDifficulty(array $ctx): string
    {
        if ($ctx['cat_all_weak_or_zero'] ?? false) {
            return 'beginner';
        }

        if (!($ctx['has_cat'] ?? false)) {
            return match ($ctx['course_level'] ?? 'junior') {
                'senior', 'middle' => 'junior_plus',
                default            => 'beginner',
            };
        }

        return match ($ctx['subtopic_skill'] ?? 'neutral') {
            'strong'  => 'junior_plus',
            'weak'    => 'beginner',
            default   => match ($ctx['course_level'] ?? 'junior') {
                'senior', 'middle' => 'junior_plus',
                default            => 'beginner',
            },
        };
    }

    public function resolveTasksRequired(array $ctx): int
    {
        return match ($ctx['learning_preference'] ?? 'balanced') {
            'practice_heavy' => 2,
            default          => 1,
        };
    }

    public function personalizationLabel(array $ctx): string
    {
        $format = match ($ctx['learning_preference'] ?? 'balanced') {
            'practice_heavy' => 'Больше практики',
            'theory_heavy'   => 'Больше теории',
            default          => 'Баланс',
        };

        if (!($ctx['has_cat'] ?? false)) {
            return "{$format} · без входного теста";
        }

        $skillPart = match ($ctx['subtopic_skill'] ?? 'neutral') {
            'strong'  => 'тест: тема сильная',
            'weak'    => 'тест: нужно закрепление',
            default   => 'тест: ' . ($ctx['overall_level'] ?? 'уровень'),
        };

        $slides = (int) ($ctx['theory_parts'] ?? 3);

        return "{$format} · {$skillPart} · {$slides} слайда теории";
    }

    public function theorySessionIntro(array $ctx, string $subtopicTitle): string
    {
        $slides = (int) ($ctx['theory_parts'] ?? 3);
        $formatNote = match ($ctx['learning_preference'] ?? 'balanced') {
            'practice_heavy' => "Формат «больше практики»: {$slides} коротких слайда теории перед задачей (теория обязательна, но компактнее, чем в других режимах).",
            'theory_heavy'   => "Формат «больше теории»: {$slides} развёрнутых слайдов с примерами и связью с практикой.",
            default          => "Формат «баланс»: {$slides} слайда теории стандартного объёма.",
        };

        if (!($ctx['has_cat'] ?? false)) {
            return <<<TEXT
{$formatNote}
Студент **не проходил** входной тест. Объясняй тему «{$subtopicTitle}» так, будто закладываешь базу в рамках курса уровня {$ctx['course_level']}: определения, мотивация, простые примеры.
Не ссылайся на результаты диагностики.
TEXT;
        }

        $skillNote = match ($ctx['subtopic_skill'] ?? 'neutral') {
            'strong' => "По входному тесту тема «{$subtopicTitle}» **уже освоена**. Не повторяй азов (что такое переменная, индекс с нуля). Дай связь с практикой, типичные ошибки и нюансы уровня {$ctx['overall_level']}.",
            'weak'   => "По входному тесту тема «{$subtopicTitle}» **требует закрепления**. Даже при коротком формате объясняй пошагово, с микро-примерами после каждой мысли.",
            default  => "По тесту общий уровень {$ctx['overall_level']}. Тема «{$subtopicTitle}» без явного strong/weak — стандартная глубина с опорой на диагностику.",
        };

        return "{$formatNote}\n{$skillNote}";
    }

    public function personalizationPrompt(array $ctx): string
    {
        $depth = $ctx['theory_depth'] ?? 'standard';
        $diff  = $ctx['task_difficulty'] ?? 'beginner';
        $tasks = $ctx['tasks_required'] ?? 1;
        $slides = $ctx['theory_parts'] ?? 3;

        $volumeRule = match ($ctx['learning_preference'] ?? 'balanced') {
            'practice_heavy' => "Объём теории: {$slides} слайда (минимальный блок перед практикой).",
            'theory_heavy'   => "Объём теории: {$slides} слайдов (максимальный блок).",
            default          => "Объём теории: {$slides} слайда (средний блок).",
        };

        $depthRule = match ($depth) {
            'advanced'     => 'Уровень объяснения: для студента с тестом, тема сильная — шпаргалка и нюансы, без базовых определений.',
            'beginner'     => 'Уровень объяснения: для слабой темы по тесту — пошагово, аналогии, микро-примеры.',
            'introductory' => 'Уровень объяснения: без теста — вводный курс, определения и мотивация с нуля.',
            default        => 'Уровень объяснения: стандарт для уровня курса с учётом теста.',
        };

        $diffRule = match ($diff) {
            'middle'      => 'Задача: Middle — реалистичный сценарий, edge cases.',
            'junior_plus' => 'Задача: Junior+ — 2–3 концепта, явные example_input/output.',
            default       => 'Задача: Beginner — один концепт, простые входные данные.',
        };

        $catLine = ($ctx['has_cat'] ?? false)
            ? 'CAT: ' . ($ctx['overall_level'] ?? '') . '. Skill подтемы: ' . ($ctx['subtopic_skill'] ?? 'neutral') . '.'
            : 'CAT не пройден.';

        return <<<TEXT
Курс «{$ctx['course_title']}» ({$ctx['course_level']}).
{$catLine}
{$volumeRule}
{$depthRule}
{$diffRule}
Задач для зачёта подтемы: {$tasks}.
TEXT;
    }

    public function personaPrompt(array $ctx): string
    {
        return match ($ctx['mentor_persona'] ?? 'colleague') {
            'strict_lead' => 'Стиль: строгий лид. Коротко, по делу. Указывай на логические ошибки и пробелы в подходе. Без похвалы и «ты молодец».',
            'soft_mentor' => 'Стиль: мягкий ментор. Поддержка, пошаговое сопровождение, ободряющий тон.',
            default       => 'Стиль: коллега. «Давай разберём», наводящие вопросы, дружелюбно но по делу.',
        };
    }

    public function languageContextPrompt(array $ctx): string
    {
        return $this->languagePrompts->buildLessonRulesFromContext($ctx);
    }

    public function mentorStylePrompt(array $ctx): string
    {
        $style = match ($ctx['learning_preference']) {
            'practice_heavy' => 'больше практики: теория короче, но всегда есть перед задачей',
            'theory_heavy'   => 'больше теории: развёрнутые слайды с примерами',
            default          => 'баланс теории и практики',
        };

        return $this->personaPrompt($ctx)
            . " Домен: {$ctx['domain_interest']}. Формат подачи: {$style}. Язык: {$ctx['direction']}."
            . "\n\n"
            . $this->languageContextPrompt($ctx);
    }

    public function domainPrompt(array $ctx): string
    {
        $key       = $ctx['domain_interest'] ?? 'general';
        $label     = self::DOMAIN_LABELS[$key] ?? self::DOMAIN_LABELS['general'];
        $scenarios = self::DOMAIN_SCENARIOS[$key] ?? self::DOMAIN_SCENARIOS['general'];

        return <<<TEXT
Контекст домена (для примеров в теории): {$label}.
Ориентир по типам данных: {$scenarios}.
Примеры в слайдах — прикладные, без рекламы домена и без чужих сюжетов (игры для PHP, финтех для highload и т.д.).
TEXT;
    }

    /** Промпт домена для практических задач — сюжет встроен незаметно. */
    public function taskDomainPrompt(array $ctx): string
    {
        $key         = $ctx['domain_interest'] ?? 'general';
        $label       = self::DOMAIN_LABELS[$key] ?? self::DOMAIN_LABELS['general'];
        $scenarios   = self::DOMAIN_SCENARIOS[$key] ?? self::DOMAIN_SCENARIOS['general'];
        $forbidden   = self::DOMAIN_FORBIDDEN[$key] ?? '';
        $direction   = mb_strtolower((string) ($ctx['direction'] ?? ''));
        $dirForbidden = self::DIRECTION_FORBIDDEN[$direction] ?? '';
        $loudWords   = implode(', ', self::DOMAIN_LOUD_WORDS[$key] ?? self::DOMAIN_LOUD_WORDS['general']);

        $forbiddenLine = $forbidden !== ''
            ? "Запрещённые сюжеты: {$forbidden}."
            : '';
        $dirLine = $dirForbidden !== ''
            ? "Для языка {$ctx['direction']} дополнительно запрещено: {$dirForbidden}."
            : '';

        return <<<TEXT
Домен «{$label}» задаёт тип задачи и имена данных, но студент НЕ должен видеть «рекламу» домена.

КАК ПИСАТЬ ЗАДАЧУ:
- title и action — про навык («Формат числа», «Срез массива»), не про домен («лайки», «соцсеть», «HP»).
- description — максимум 1 нейтральное предложение или пустая строка; без «вы разрабатываете…», «представьте…», «в вашей соцсети…».
- Контекст домена — через function_signature, constraints и test_cases (offset, limit, amountCents, previewLen), не через легенду.
- Типичные операции домена (для ИИ): {$scenarios}.

ЗАПРЕЩЕНО в title, description, action:
- Явно называть домен, продукт или индустрию ({$loudWords}).
- Легенды и role-play («ты делаешь нейросеть», «сервис лайков»).
{$forbiddenLine}
{$dirLine}
TEXT;
    }

    public function taskToneRules(array $ctx): string
    {
        return <<<'TEXT'
Тон задачи — техническое ТЗ, как в codewars/leetcode с прикладными данными:
- Студент читает action + signature + constraints + test_cases и сразу понимает, что писать.
- Не объясняй, в какой индустрии он работает — это и так следует из данных.
TEXT;
    }

    public function contentFormatRules(): string
    {
        return <<<'TEXT'
Формат текста (строго):
- Без emoji
- Без заголовков markdown (#)
- Код только в ```язык ... ``` или `инлайн` — язык блока = язык курса из контекста
- Акценты — **двойными звёздочками**
- Списки — дефис «-»
- content: 3–5 абзацев по 2–4 предложения
- Определения, «зачем», типичные ошибки, связь с практикой
- Примеры кода и playground — только синтаксис и API выбранного языка курса
TEXT;
    }

    public function mentorDialogRules(): string
    {
        return <<<'TEXT'
Правила диалога ментора (строго):
- Адаптируйся под язык программирования из контекста урока; не упоминай другие языки без необходимости
- ЗАПРЕЩЕНО ссылаться на номера строк («на строке 15», «line 42»). Используй контекст: «в цикле фильтрации», «в функции сортировки», «в блоке обработки ошибок»
- ЗАПРЕЩЕНО выдавать готовое решение задачи целиком, пока студент явно не попросил «напиши код за меня»
- Направляй мышление: вопросы, план, типичные ошибки, мини-примеры архитектуры — без финального кода решения
- Весь текст только в чат; не дублируй в вывод программы
- Без emoji
TEXT;
    }

    public function theoryDepthRules(array $ctx): string
    {
        return match ($ctx['theory_depth'] ?? 'standard') {
            'advanced'     => 'Слайды — cheat sheet для знающего. Advanced-нюансы. Не объясняй базовые определения. playground с фрагментами кода где уместно.',
            'beginner'     => 'Каждый абзац — один шаг. Аналогии из жизни. playground на слайдах с кодом.',
            'introductory' => 'Студент без теста: определения, мотивация «зачем», простые примеры с нуля. playground с кодом обязателен на большинстве слайдов.',
            default        => 'Стандартная глубина для уровня курса. playground с примером кода на большинстве слайдов.',
        };
    }

    public function theoryBatchJsonSchema(int $totalParts, string $direction): string
    {
        $lang = strtolower($direction);

        return <<<TEXT
Верни JSON:
{
  "slides": [
    {
      "mentor_opener": "1–2 предложения (только slides[0])",
      "slide_title": "заголовок шага",
      "content": "текст слайда",
      "callout": "ключевое правило или null",
      "right_panel_mode": "playground|trace|none",
      "playground": {
        "code": "минимальный рабочий пример 5–15 строк",
        "language": "{$lang}",
        "stdin": ""
      },
      "trace_steps": [
        {"line": 1, "highlight": "int arr[3]", "memory": [{"name": "arr", "value": "[10,20,30]"}]}
      ]
    }
  ]
}
Ровно {$totalParts} слайдов. right_panel_mode=playground для примеров кода; trace для пошаговой памяти (2–5 steps); none только если нечего показать справа.
playground обязателен когда mode=playground. trace_steps=null если mode не trace. Схемы PlantUML не используй.
Последний слайд готовит к практике.
TEXT;
    }

    public function theorySlideJsonSchema(string $direction): string
    {
        $lang = strtolower($direction);

        return <<<TEXT
Верни JSON:
{
  "mentor_opener": "только на шаге 1",
  "slide_title": "заголовок",
  "content": "текст",
  "callout": "или null",
  "right_panel_mode": "playground|trace|none",
  "playground": {"code": "...", "language": "{$lang}", "stdin": ""},
  "trace_steps": [{"line": 1, "highlight": "...", "memory": [{"name": "x", "value": "0"}]}],
  "has_more": true/false
}
TEXT;
    }

    public function taskBriefRules(array $ctx): string
    {
        $dir = $ctx['direction'] ?? 'PHP';
        $signatureExample = $this->languagePrompts->functionSignatureExample($dir);

        return <<<TEXT
Техническое задание — строго по структуре. Без обязательных полей задача недействительна.

1. title — название навыка (до 8 слов): «Срез массива», «Округление суммы». НЕ «лайки в соцсети», НЕ «HP персонажа».
2. description — необязательно; если есть — одно нейтральное предложение без легенды и без названия домена. Можно "".
3. action — одно предложение «Напишите функцию …» с именем из function_signature.
4. function_signature — точная сигнатура на {$dir} (пример формата: {$signatureExample}).
5. constraints — 2–4 правила: типы; edge cases (пусто, ноль, отрицательное, переполнение); бизнес-правило через данные, не через «вы в банке…».
6. test_cases — ≥3 примера {label, input, output} с конкретными значениями.
7. starter_code — каркас на {$dir} с signature и TODO; синтаксис только языка курса.

Запрещено: легенды «разрабатываете модуль/игру/соцсеть»; задачи-словарь («посчитай лайки»); проза без signature и test_cases; PHP/Java синтаксис в курсе не-PHP.
TEXT;
    }

    public function taskJsonSchema(array $ctx): string
    {
        $diff = $ctx['task_difficulty'] ?? 'beginner';
        $dir  = $ctx['direction'] ?? 'PHP';
        $signatureExample = $this->languagePrompts->functionSignatureExample($dir);
        $minCases = match ($diff) {
            'middle', 'junior_plus' => 3,
            default                 => 3,
        };

        return <<<TEXT
Верни ТОЛЬКО JSON — один объект задачи (без обёртки "task"):
{
  "title": "Название навыка (не домена)",
  "description": "Пустая строка или 1 нейтральное предложение без легенды",
  "action": "Напишите функцию <имя>(...) которая …",
  "function_signature": "{$signatureExample}",
  "constraints": [
    "Правило типов для {$dir}",
    "Если … (крайний случай) — вернуть …",
    "Ещё одно бизнес-правило домена"
  ],
  "test_cases": [
    {"label": "Базовый случай", "input": "конкретные аргументы", "output": "конкретный результат"},
    {"label": "Граничный случай", "input": "…", "output": "…"},
    {"label": "Ещё один случай", "input": "…", "output": "…"}
  ],
  "starter_code": "каркас на {$dir} с function_signature и TODO"
}

Обязательно: function_signature не пустая; constraints ≥ 2; test_cases ≥ {$minCases} с реальными input/output.
Язык: {$dir}. Сложность: {$diff}. Одна функция — студент дописывает только её тело. Синтаксис starter_code = синтаксис {$dir}.
TEXT;
    }

    public function taskValidationReminder(array $ctx): string
    {
        $key   = $ctx['domain_interest'] ?? 'general';
        $label = self::DOMAIN_LABELS[$key] ?? self::DOMAIN_LABELS['general'];

        return <<<TEXT
ПРОВЕРЬ ОТВЕТ:
- function_signature не пустая; constraints ≥ 2; test_cases ≥ 3 с конкретными input/output.
- title/action — про навык, без слов домена и без игровых сюжетов (HP, RPG) для PHP.
- description без «вы разрабатываете…», без «соцсеть/лайк/нейросеть».
- starter_code и playground — синтаксис языка «{$ctx['direction']}», не другого языка.
- Домен «{$label}» чувствуется только в данных (имена параметров, test_cases), не в легенде.
TEXT;
    }

    /** @return list<string> */
    public function taskForbiddenPhrases(array $ctx): array
    {
        $key       = $ctx['domain_interest'] ?? 'general';
        $direction = mb_strtolower((string) ($ctx['direction'] ?? ''));
        $phrases   = self::DOMAIN_LOUD_WORDS[$key] ?? [];

        $meta = ['разрабатываете', 'представьте', 'представь что', 'ты делаешь', 'ваш сервис', 'ваше приложение'];

        if (isset(self::DIRECTION_FORBIDDEN[$direction])) {
            foreach (explode(',', self::DIRECTION_FORBIDDEN[$direction]) as $part) {
                $phrases[] = mb_strtolower(trim($part));
            }
        }

        return array_values(array_unique(array_filter(array_merge($phrases, $meta))));
    }
}
