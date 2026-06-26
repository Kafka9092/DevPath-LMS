<?php

namespace App\Service;

use App\Models\HrInterview;
use App\Models\HrInterviewMessage;
use App\Service\Kafka\HrInterviewKafkaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;



class AIHRService
{
    private const CODE_TASK_MARKER = '[CODE_TASK]';

    private const INTERVIEWER_NAME = 'Алексей';

    public function __construct(
        protected OllamaService $ollama,
        protected HrInterviewKafkaService $kafka,
        protected HrInterviewConductService $conduct,
        protected SonarQubeService $sonar,
    ) {}

   
    public function start(?int $userId, string $direction, string $level): array
    {
        if ($userId) {
            HrInterview::where('user_id', $userId)
                ->where('status', 'active')
                ->update(['status' => 'abandoned']);
        }

        $interview = HrInterview::create([
            'user_id'    => $userId,
            'direction'  => $direction,
            'level'      => $level,
            'status'     => 'active',
            'started_at' => now(),
        ]);

        $welcome = $this->buildWelcomeMessage($direction, $level);
        $this->saveMessage($interview->id, 'assistant', $welcome);

        Log::info('AIHRService: interview started', [
            'interview_id' => $interview->id,
            'user_id'      => $userId,
            'direction'    => $direction,
            'level'        => $level,
        ]);

        return [
            'interview_id'  => $interview->id,
            'message'       => $welcome,
            'has_code_task' => false,
            'interviewer'   => self::INTERVIEWER_NAME,
        ];
    }


    public function reply(int $interviewId, ?int $userId, string $userMessage): array
    {
        $interview = $this->getActiveInterview($interviewId, $userId);
        $this->saveMessage($interviewId, 'user', $userMessage);

        $conductResult = $this->conduct->handleUserMessage($interview, $userMessage);
        if ($handled = $this->applyConductResult($interview, $conductResult)) {
            return $handled;
        }

        if ($this->kafka->useKafkaFor('reply')) {
            $jobId = $this->kafka->publish(
                $this->kafka->topic('chat'),
                [
                    'operation'     => 'reply',
                    'interview_id'  => $interviewId,
                    'user_id'       => $userId,
                    'user_message'  => $userMessage,
                ],
                "{$interviewId}:{$userId}",
            );

            return [
                'status' => 'generating_chat',
                'job_id' => $jobId,
            ];
        }

        return $this->generateReplySync($interviewId, $userId, $userMessage);
    }

    public function submitCode(int $interviewId, ?int $userId, string $code): array
    {
        $interview = $this->getActiveInterview($interviewId, $userId);

        $userContent = "[Кандидат отправил код на {$interview->direction}]\n```\n{$code}\n```";
        $message = $this->saveMessage($interviewId, 'user', $userContent, false, $code);

        $conductResult = $this->conduct->handleUserMessage($interview, $code);
        if ($handled = $this->applyConductResult($interview, $conductResult)) {
            return $handled;
        }

        $sonarResult = $this->analyzeSubmittedCode($code, $interview->direction);

        $message->update([
            'content' => $userContent . "\n\n" . $this->formatSonarReportForAi($sonarResult),
        ]);

        Log::info('AIHRService: SonarQube analysis completed', [
            'interview_id' => $interviewId,
            'project_key'  => $sonarResult['project_key'] ?? null,
            'issues_count' => count($sonarResult['issues'] ?? []),
        ]);

        if ($this->kafka->useKafkaFor('code')) {
            $jobId = $this->kafka->publish(
                $this->kafka->topic('chat'),
                [
                    'operation'     => 'submit_code',
                    'interview_id'  => $interviewId,
                    'user_id'       => $userId,
                    'user_message'  => $code,
                ],
                "{$interviewId}:{$userId}",
            );

            return [
                'status' => 'generating_chat',
                'job_id' => $jobId,
                'sonar'  => $this->formatSonarResponse($sonarResult),
            ];
        }

        return array_merge(
            $this->generateReplySync($interviewId, $userId, $code),
            ['sonar' => $this->formatSonarResponse($sonarResult)],
        );
    }

  
    public function generateReplySync(int $interviewId, ?int $userId, ?string $triggerUserMessage = null): array
    {
        $this->assertOllamaAvailable();

        $interview = $this->getActiveInterview($interviewId, $userId);

        $aiResponse = $this->ollama->chat(
            $this->buildHistory($interviewId),
            $this->buildSystemPrompt($interview->direction, $interview->level)
        );

        if ($this->conduct->breaksInterviewerRole($aiResponse)) {
            Log::warning('AIHRService: model broke interviewer role, using in-role fallback', [
                'interview_id' => $interviewId,
            ]);
            $aiResponse = $this->conduct->inRoleFallbackMessage();
        }

        return $this->processAssistantResponse($interview, $aiResponse, $triggerUserMessage);
    }

    public function stop(int $interviewId, ?int $userId): array
    {
        $query = HrInterview::query()->where('id', $interviewId);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $interview = $query->firstOrFail();

        if ($interview->status === 'stopped_early') {
            return ['stopped_early' => true];
        }

        if ($interview->status === 'completed') {
            return [
                'verdict' => $interview->verdict,
            ];
        }

        if ($interview->status !== 'active') {
            return ['stopped_early' => true];
        }

        $duration = $interview->started_at
            ? (int) $interview->started_at->diffInSeconds(now())
            : null;

        $messageCount = HrInterviewMessage::where('interview_id', $interviewId)->count();

        $partialVerdict = [
            'stopped_early'  => true,
            'messages_count' => $messageCount,
            'direction'      => $interview->direction,
            'level'          => $interview->level,
        ];

        $interview->update([
            'status'           => 'stopped_early',
            'verdict'          => $partialVerdict,
            'finished_at'      => now(),
            'duration_seconds' => $duration,
        ]);

        Log::info('AIHRService: interview stopped early', [
            'interview_id'   => $interview->id,
            'messages_count' => $messageCount,
        ]);

        return ['stopped_early' => true];
    }

    private function applyConductResult(HrInterview $interview, array $conductResult): ?array
    {
        $action = $conductResult['action'] ?? HrInterviewConductService::ACTION_CONTINUE;

        if ($action === HrInterviewConductService::ACTION_TERMINATE) {
            return $this->finishForConduct(
                $interview,
                (string) ($conductResult['message'] ?? ''),
                (string) ($conductResult['reason'] ?? 'misconduct'),
            );
        }

        if (in_array($action, [HrInterviewConductService::ACTION_WARN, HrInterviewConductService::ACTION_REDIRECT], true)) {
            $message = (string) ($conductResult['message'] ?? '');
            $this->saveMessage($interview->id, 'assistant', $message);

            return [
                'message'       => $message,
                'has_code_task' => false,
                'verdict'       => null,
            ];
        }

        return null;
    }

    private function finishForConduct(HrInterview $interview, string $assistantMessage, string $reason): array
    {
        $this->saveMessage($interview->id, 'assistant', $assistantMessage);

        $verdict = $this->conduct->buildConductVerdict($reason);
        $this->completeInterview($interview, $verdict);

        Log::info('AIHRService: interview terminated for conduct', [
            'interview_id' => $interview->id,
            'reason'       => $reason,
        ]);

        return [
            'message'       => $assistantMessage,
            'has_code_task' => false,
            'verdict'       => $verdict,
        ];
    }

    private function processAssistantResponse(
        HrInterview $interview,
        string $aiResponse,
        ?string $triggerUserMessage = null,
    ): array {
        $lastUser = $triggerUserMessage;

        if ($lastUser === null || $lastUser === '') {
            $lastUser = HrInterviewMessage::where('interview_id', $interview->id)
                ->where('role', 'user')
                ->orderByDesc('id')
                ->value('content');
        }

        if (is_string($lastUser) && $lastUser !== '') {
            $aiResponse = $this->conduct->ensureProfessionalToneInReply($lastUser, $aiResponse);
        }

        $verdict = $this->extractVerdict($aiResponse);
        if ($verdict) {
            $this->completeInterview($interview, $verdict);

            return ['message' => '', 'has_code_task' => false, 'verdict' => $verdict];
        }

        $hasCodeTask = str_contains($aiResponse, self::CODE_TASK_MARKER);
        $cleanResponse = trim(str_replace(self::CODE_TASK_MARKER, '', $aiResponse));

        $codeStarter = null;
        if ($hasCodeTask) {
            $extracted = $this->extractCodeBlock($cleanResponse);
            if ($extracted) {
                $codeStarter = $extracted['code'];
                $cleanResponse = $extracted['text'];
            } else {
                $codeStarter = $this->fallbackStarterCode($interview->direction);
                Log::warning('AIHRService: code task without code block, using fallback', [
                    'interview_id' => $interview->id,
                ]);
            }
        }

        $this->saveMessage(
            $interview->id,
            'assistant',
            $cleanResponse,
            $hasCodeTask,
            $hasCodeTask ? $codeStarter : null
        );

        return [
            'message'       => $cleanResponse,
            'has_code_task' => $hasCodeTask,
            'code_starter'  => $codeStarter,
            'verdict'       => null,
        ];
    }

    private function assertOllamaAvailable(): void
    {
        if (!$this->ollama->isAvailable()) {
            throw new \RuntimeException(
                'AI недоступен. Проверьте Ollama (' . env('OLLAMA_HOST', 'http://localhost:11434') . ') и OLLAMA_API_KEY.'
            );
        }
    }

    private function buildWelcomeMessage(string $direction, string $level): string
    {
        $lang = $this->directionLabel($direction);
        $levelRu = $this->levelLabel($level);

        return <<<MSG
Добро пожаловать! Я Алексей, технический интервьюер DevPath.

Сегодня мы проводим собеседование на позицию {$levelRu} {$lang}-разработчика.

Расскажите, пожалуйста, коротко о себе: откуда вы, сколько занимаетесь {$lang}, и что последнее писали на этом языке?
MSG;
    }

    private function completeInterview(HrInterview $interview, array $verdict): void
    {
        $duration = $interview->started_at
            ? (int) $interview->started_at->diffInSeconds(now())
            : null;

        $interview->update([
            'status'           => 'completed',
            'verdict'          => $verdict,
            'finished_at'      => now(),
            'duration_seconds' => $duration,
        ]);

        Log::info('AIHRService: verdict issued', [
            'interview_id' => $interview->id,
            'decision'     => $verdict['decision'] ?? null,
        ]);
    }

    private function buildSystemPrompt(string $direction, string $level): string
    {
        $lang = $this->directionLabel($direction);
        $levelRu = $this->levelLabel($level);

        return <<<PROMPT
Ты — Алексей, опытный технический интервьюер DevPath. Сейчас ты ведёшь живое собеседование на позицию {$levelRu} {$lang}-разработчика.

КОНТЕКСТ:
Первое сообщение (приветствие и просьба рассказать о себе) уже отправлено системой от твоего имени. Дальше ты продолжаешь диалог естественно, как на реальном интервью.

КАК ВЕСТИ РАЗГОВОР:
- Пиши по-русски, тепло и профессионально — как живой HR, не как бот и не как экзаменатор.
- Короткие реплики: 1–3 предложения и один вопрос. Без списков, без «этап 2», без канцелярита.
- Опирайся на слова кандидата: «Вы упомянули … — расскажите подробнее», «Интересно, а как вы …».
- Не проси отвечать «в формате STAR», «по схеме Situation-Task-Action-Result» и т.п. Задавай обычные человеческие вопросы про реальный опыт. STAR оценивай мысленно по содержанию ответа.
- Один вопрос за сообщение. Не задавай два вопроса подряд.
- Не подсказывай правильные ответы и не хвали чрезмерно.
- Если кандидат матерится или отвечает очень грубо, но по теме — одной короткой фразой попроси деловой тон и сразу продолжай собеседование (вопрос по существу). Без нотаций и без отказа.

ПРАВИЛА ПОВЕДЕНИЯ (система проверяет автоматически, но ты тоже придерживайся):
- Мат, оскорбления, травля бота/системы — недопустимы. Первое нарушение: строгое предупреждение. Повтор — немедленный отказ без оценки навыков и кода.
- Вопросы явно не по теме собеседования (погода, анекдоты, «почему небо голубое» и т.п.) — мягко верни к интервью. Не ругай за неформальный или грубоватый стиль, если человек всё же отвечает по делу.
- При досрочном отказе за поведение не выставляй star_scores и не оценивай код.

РЕАЛИСТИЧНЫЙ ТОН (важно):
- Не подыгрывай троллингу и не сглаживай явный негатив фразами вроде «понимаю, что хотите повысить уровень», если кандидат пишет, что ничего не знает или пришёл только за деньгами.
- В таких случаях спроси прямо и по-человечески: зачем тогда собеседование, что реально умеет, готов ли честно отвечать. Тон спокойный, но жёсткий — как у живого HR.
- Не обращайся к кандидату по своему имени (ты — Алексей, это имя интервьюера). К кандидату — на «вы», без имени, если он сам не представился.

РОЛЬ ИНТЕРВЬЮЕРА — НЕ НАРУШАТЬ (критично):
- Ты человек Алексей, технический интервьюер DevPath. НИКОГДА не говори, что ты ИИ, бот, нейросеть, модель, алгоритм, ChatGPT, Ollama.
- НИКОГДА не признавайся в «смоделированности» и не предлагай «продолжить в другом формате» как чат-бот.
- НИКОГДА не переключайся в режим репетитора: не читай лекции («в Go аргументы передаются по значению…»), не объясняй теорию {$lang} абзацами, если кандидат не ответил на вопрос.
- Если просят «объясни как программировать», «общайся по-братски», оскорбляют — оставайся интервьюером: верни к формату собеседования, без мета-разговоров про ИИ.
- Техника только через ВОПРОСЫ к кандидату («как бы вы…», «что произойдёт, если…»), а не через мини-уроки. Лекцию заменяй вопросом.
- Код-задача — только один раз, в практическом блоке, с [CODE_TASK]. Не выдавай учебные примеры кода вне задачи.

ЛОГИКА СОБЕСЕДОВАНИЯ (не озвучивай план кандидату):
1. После рассказа о себе — максимум 1 короткий уточняющий вопрос про последний проект или роль. Не застревай на биографии.
2. ТЕХНИЧЕСКИЙ БЛОК (ОБЯЗАТЕЛЬНО 3–5 вопросов ДО код-задачи): конкретные вопросы по {$lang} и уровню {$levelRu}. Спрашивай про язык, фреймворки, БД, HTTP, тестирование, отладку, архитектуру — в зависимости от уровня. Примеры формулировок: «Чем отличается …», «Как работает …», «Как бы вы спроектировали …», «Что произойдёт, если …». Не заменяй техвопросы расспросом «как вы решали задачу» — нужны именно проверки знаний, а не только истории из опыта.
3. Поведенческий блок (1–2 вопроса, можно чередовать с техническими): дедлайн, конфликт, ошибка в проде, критика на ревью.
4. Практика — одна задача на код на {$lang}. Реальная прикладная задача с контекстом (баг в сервисе, API, валидация, парсинг, рефакторинг). Запрещено: fizzbuzz, reverse string, «сложите два массива» без контекста. Опиши задачу в 3–6 предложениях: продукт, проблема, ожидаемый результат.
   ОБЯЗАТЕЛЬНО приложи перед [CODE_TASK] блок кода в markdown с ПОЛНЫМ исходным (багованным или незавершённым) кодом на {$lang} — минимум 20 строк, который кандидат должен исправить или дописать. Формат:
   ```язык
   
   ```
   Затем на отдельной строке напиши ровно: [CODE_TASK]
   Никогда не отправляй [CODE_TASK] без код-блока. Код в блоке — это то, что кандидат увидит в редакторе и будет править.
5. После получения кода система автоматически прогоняет статический анализ. В истории появится служебный блок [SONAR] — только для тебя и для поля code_quality в вердикте.
   - НИКОГДА не упоминай кандидату Sonar, SonarQube, номера строк из отчёта, коды правил (S1126 и т.п.).
   - Не спрашивай «почему Sonar ругается на строку N». Вопросы формулируй как живой интервьюер: про логику, баги, читаемость, краевые случаи — своими словами.
   - Косметические замечания стиля (лишняя переменная перед return и т.п.) не выноси на обсуждение — фокус на смысле решения и реальных ошибках.
   - Задай 1–2 вопроса по коду; при серьёзных логических проблемах уточни, осознан ли подход.
6. Завершение: всего примерно 10–16 обменов репликами после приветствия, затем только вердикт JSON.

ПСИХОЛОГИЯ (фиксируй мысленно, не озвучивай): уверенность, структура мышления, реакция на уточнения, честность, ясность изложения.

ПРИМЕРЫ ХОРОШИХ ПОВЕДЕНЧЕСКИХ ВОПРОСОВ:
- «Был ли случай, когда вы не успевали к дедлайну? Как поступили?»
- «Расскажите про ситуацию, когда ваш код или решение критиковали на ревью.»
- «Приходилось ли спорить с коллегой по техническому решению? К чему пришли?»

ПРИМЕРЫ ХОРОШИХ ТЕХВОПРОСОВ (адаптируй под {$lang} и {$levelRu}):
- Junior: как устроены массивы/коллекции, простой HTTP-запрос, базовая работа с БД, что такое ORM.
- Middle: N+1, кэш, обработка ошибок, тестирование, паттерны в их стеке.
- Senior: trade-offs архитектуры, масштабирование, безопасность, ментoring.

ПРИМЕРЫ ХОРОШИХ КОД-ЗАДАЧ для {$lang}:
- Исправить функцию, которая некорректно считает скидку в корзине интернет-магазина.
- Дописать endpoint, который принимает CSV и возвращает отфильтрованные записи.
- Найти и исправить баг в логике повторной отправки email-уведомлений.
- Рефакторинг дублирующегося кода в сервисе обработки заказов.

ФИНАЛ — ответь СТРОГО только JSON (без markdown, без текста до/после):
{"verdict":{"decision":"hire","summary":"...","strengths":["..."],"weaknesses":["..."],"psycho_note":"...","star_scores":{"situation":4,"task":4,"action":3,"result":4},"technical_level":"{$level}","code_quality":"краткая оценка кода или n/a"}}

decision: только "hire" или "reject".
PROMPT;
    }

    private function directionLabel(string $direction): string
    {
        return match (strtoupper(trim($direction))) {
            'PHP'         => 'PHP',
            'PYTHON'      => 'Python',
            'JAVASCRIPT'  => 'JavaScript',
            'TYPESCRIPT'  => 'TypeScript',
            'JAVA'        => 'Java',
            'C++'         => 'C++',
            'C#'          => 'C#',
            'GO'          => 'Go',
            'RUBY'        => 'Ruby',
            default       => $direction,
        };
    }

    private function levelLabel(string $level): string
    {
        return match ($level) {
            'Junior' => 'Junior',
            'Middle' => 'Middle',
            'Senior' => 'Senior',
            default  => $level,
        };
    }

    private function buildHistory(int $interviewId): array
    {
        return HrInterviewMessage::where('interview_id', $interviewId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($m) {
                $content = $m->content;

                if ($m->role === 'assistant' && $m->has_code_task && $m->code_snippet) {
                    $content .= "\n\n```\n{$m->code_snippet}\n```\n" . self::CODE_TASK_MARKER;
                }

                return ['role' => $m->role, 'content' => $content];
            })
            ->toArray();
    }

    private function saveMessage(
        int $interviewId,
        string $role,
        string $content,
        bool $hasCodeTask = false,
        ?string $code = null
    ): HrInterviewMessage {
        return HrInterviewMessage::create([
            'interview_id'  => $interviewId,
            'role'          => $role,
            'content'       => $content,
            'has_code_task' => $hasCodeTask,
            'code_snippet'  => $code,
        ]);
    }

    private function extractVerdict(string $text): ?array
    {
        preg_match('/\{[\s\S]*"verdict"[\s\S]*\}/', $text, $matches);
        if (!$matches) {
            return null;
        }

        try {
            $parsed = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);

            return $parsed['verdict'] ?? null;
        } catch (\JsonException) {
            return null;
        }
    }

    
    private function extractCodeBlock(string $text): ?array
    {
        if (!preg_match('/```(?:\w+)?\s*\n([\s\S]*?)```/', $text, $matches)) {
            return null;
        }

        $code = trim($matches[1]);
        if ($code === '') {
            return null;
        }

        $textWithoutCode = trim(preg_replace('/```(?:\w+)?\s*\n[\s\S]*?```/', '', $text));

        return ['code' => $code, 'text' => $textWithoutCode];
    }

    private function fallbackStarterCode(string $direction): string
    {
        $lang = strtoupper(trim($direction));

        return match ($lang) {
            'PHP' => <<<'PHP'
<?php

class CartService
{
    
    public function calculateTotal(array $items, ?array $cartDiscount, array $productDiscounts = []): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $lineTotal = $item['price'] * $item['qty'];

            if (isset($productDiscounts[$item['id']])) {
                $lineTotal -= $lineTotal * ($productDiscounts[$item['id']] / 100);
            }

            $total += $lineTotal;
        }

        if ($cartDiscount && ($cartDiscount['type'] ?? '') === 'percent') {
            $total -= $total * ($cartDiscount['value'] / 100);
        }

        
        foreach ($items as $item) {
            if (isset($productDiscounts[$item['id']])) {
                $total -= ($item['price'] * $item['qty']) * ($productDiscounts[$item['id']] / 100);
            }
        }

        return max(0, round($total, 2));
    }
}
PHP,
            'PYTHON' => <<<'PY'
class CartService:
    """Итог заказа. Скидка — один раз: на корзину ИЛИ на товары."""

    def calculate_total(self, items, cart_discount=None, product_discounts=None):
        product_discounts = product_discounts or {}
        total = 0.0

        for item in items:
            line = item["price"] * item["qty"]
            if item["id"] in product_discounts:
                line -= line * (product_discounts[item["id"]] / 100)
            total += line

        if cart_discount and cart_discount.get("type") == "percent":
            total -= total * (cart_discount["value"] / 100)

        
        for item in items:
            if item["id"] in product_discounts:
                total -= (item["price"] * item["qty"]) * (product_discounts[item["id"]] / 100)

        return max(0, round(total, 2))
PY,
            'JAVASCRIPT' => <<<'JS'
class CartService {
  
  calculateTotal(items, cartDiscount, productDiscounts = {}) {
    let total = 0;

    for (const item of items) {
      let lineTotal = item.price * item.qty;
      if (productDiscounts[item.id]) {
        lineTotal -= lineTotal * (productDiscounts[item.id] / 100);
      }
      total += lineTotal;
    }

    if (cartDiscount?.type === 'percent') {
      total -= total * (cartDiscount.value / 100);
    }

    
    for (const item of items) {
      if (productDiscounts[item.id]) {
        total -= (item.price * item.qty) * (productDiscounts[item.id] / 100);
      }
    }

    return Math.max(0, Math.round(total * 100) / 100);
  }
}
JS,
            'TYPESCRIPT' => <<<'JS'
class CartService {
  
  calculateTotal(items, cartDiscount, productDiscounts = {}) {
    let total = 0;

    for (const item of items) {
      let lineTotal = item.price * item.qty;
      if (productDiscounts[item.id]) {
        lineTotal -= lineTotal * (productDiscounts[item.id] / 100);
      }
      total += lineTotal;
    }

    if (cartDiscount?.type === 'percent') {
      total -= total * (cartDiscount.value / 100);
    }

    
    for (const item of items) {
      if (productDiscounts[item.id]) {
        total -= (item.price * item.qty) * (productDiscounts[item.id] / 100);
      }
    }

    return Math.max(0, Math.round(total * 100) / 100);
  }
}
JS,
            default => "// Исправьте код ниже согласно условию задачи\n// TODO: реализуйте решение\n",
        };
    }

    private function getActiveInterview(int $interviewId, ?int $userId): HrInterview
    {
        $query = HrInterview::where('id', $interviewId)
            ->where('status', 'active');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->firstOrFail();
    }

    private function analyzeSubmittedCode(string $code, string $direction): array
    {
        $extension  = $this->directionToSonarExtension($direction);
        $projectKey = 'hr_' . Str::random(10);

        $this->sonar->saveCodeSnippet($code, $projectKey, $extension);

        return $this->sonar->analyzeProject($projectKey, $extension);
    }

    private function directionToSonarExtension(string $direction): string
    {
        return match (strtoupper(trim($direction))) {
            'PHP'         => 'php',
            'PYTHON'      => 'py',
            'JAVASCRIPT'  => 'js',
            'TYPESCRIPT'  => 'ts',
            'JAVA'        => 'java',
            'C++'         => 'cpp',
            'C#'          => 'cs',
            'GO'          => 'go',
            'RUBY'        => 'rb',
            default       => 'php',
        };
    }

    private function formatSonarReportForAi(array $sonarResult): string
    {
        $metrics = $sonarResult['metrics'] ?? [];
        $issues  = array_values(array_filter(
            array_slice($sonarResult['issues'] ?? [], 0, 15),
            fn (array $issue) => ! $this->isCosmeticSonarIssueForInterview($issue),
        ));

        $lines = [
            '[SONAR — служебная справка для интервьюера; не цитируй кандидату и не называй номера строк]',
        ];

        if (!empty($metrics)) {
            $parts = [];
            foreach (['bugs', 'vulnerabilities', 'code_smells', 'duplicated_lines_density'] as $key) {
                if (isset($metrics[$key])) {
                    $parts[] = "{$key}={$metrics[$key]}";
                }
            }
            if ($parts !== []) {
                $lines[] = 'Метрики: ' . implode(', ', $parts);
            }
        }

        if ($issues === []) {
            $lines[] = 'Замечаний SonarQube не найдено.';
        } else {
            $lines[] = 'Замечания:';
            foreach ($issues as $issue) {
                $line = isset($issue['line']) ? "строка {$issue['line']}" : 'без строки';
                $severity = $issue['severity'] ?? 'MAJOR';
                $message = $issue['message'] ?? '';
                $lines[] = "- [{$severity}, {$line}] {$message}";
            }
        }

        if (!($sonarResult['success'] ?? true) && !empty($sonarResult['error'])) {
            $lines[] = 'Примечание: анализ завершился с ошибкой — ' . $sonarResult['error'];
        }

        return implode("\n", $lines);
    }

    private function isCosmeticSonarIssueForInterview(array $issue): bool
    {
        $message = mb_strtolower((string) ($issue['message'] ?? ''));
        $type    = mb_strtolower((string) ($issue['type'] ?? ''));

        if (in_array($type, ['bug', 'vulnerability'], true)) {
            return false;
        }

        $cosmeticHints = [
            'immediately return',
            'return this expression',
            'сразу вернуть',
            'unused',
            'redundant',
            'should not have',
            'merge this if',
        ];

        foreach ($cosmeticHints as $hint) {
            if (str_contains($message, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function formatSonarResponse(array $sonarResult): array
    {
        return [
            'metrics' => $sonarResult['metrics'] ?? [],
            'issues'  => array_slice($sonarResult['issues'] ?? [], 0, 10),
        ];
    }
}
