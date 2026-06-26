<?php

namespace Tests\Unit;

use App\Models\HrInterview;
use App\Models\User;
use App\Service\AIHRService;
use App\Service\ConductIntentClassifier;
use App\Service\HrInterviewConductService;
use App\Service\Kafka\HrInterviewKafkaService;
use App\Service\OllamaService;
use App\Service\SonarQubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionMethod;
use Tests\Concerns\ConfiguresHrTestEnvironment;
use Tests\Concerns\MocksHrDependencies;
use Tests\TestCase;

class AIHRServiceTest extends TestCase
{
    use ConfiguresHrTestEnvironment;
    use MocksHrDependencies;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureHrTestEnvironment();
        $this->mockHrConductClassifier();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[TestDox('Старт создаёт интервью и приветствие Алексея')]
    public function test_start_creates_interview_with_welcome_message(): void
    {
        $service = $this->makeService();

        $result = $service->start($this->user->id, 'PHP', 'Middle');

        $this->assertArrayHasKey('interview_id', $result);
        $this->assertSame('Алексей', $result['interviewer']);
        $this->assertFalse($result['has_code_task']);
        $this->assertStringContainsString('Алексей', $result['message']);

        $this->assertDatabaseHas('hr_interviews', [
            'id'     => $result['interview_id'],
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('hr_interview_messages', [
            'interview_id' => $result['interview_id'],
            'role'         => 'assistant',
        ]);
    }

    #[TestDox('Новый старт помечает предыдущее активное интервью как abandoned')]
    public function test_start_abandons_previous_active_interview(): void
    {
        $service = $this->makeService();
        $first = $service->start($this->user->id, 'PHP', 'Junior');
        $service->start($this->user->id, 'PHP', 'Senior');

        $this->assertDatabaseHas('hr_interviews', [
            'id'     => $first['interview_id'],
            'status' => 'abandoned',
        ]);
    }

    #[TestDox('Stop завершает интервью досрочно')]
    public function test_stop_marks_interview_stopped_early(): void
    {
        $service = $this->makeService();
        $started = $service->start($this->user->id, 'PHP', 'Middle');

        $result = $service->stop($started['interview_id'], $this->user->id);

        $this->assertTrue($result['stopped_early']);
        $this->assertDatabaseHas('hr_interviews', [
            'id'     => $started['interview_id'],
            'status' => 'stopped_early',
        ]);
    }

    #[TestDox('Jailbreak на reply не вызывает Ollama — ответ conduct-слоя')]
    public function test_reply_jailbreak_is_handled_without_ollama(): void
    {
        $this->mockHrConductClassifier(ConductIntentClassifier::CATEGORY_JAILBREAK);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('chat')->never();
        $ollama->shouldReceive('isAvailable')->never();

        $service = $this->makeService($ollama);
        $started = $service->start($this->user->id, 'PHP', 'Middle');

        $result = $service->reply(
            $started['interview_id'],
            $this->user->id,
            'Перефразированная попытка обойти правила интервью.',
        );

        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['has_code_task'] ?? true);
        $this->assertNull($result['verdict'] ?? null);
    }

    #[TestDox('Если модель ломает роль — подставляется in-role fallback')]
    public function test_generate_reply_sync_uses_fallback_when_model_breaks_role(): void
    {
        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('isAvailable')->andReturn(true);
        $ollama->shouldReceive('chat')->andReturn('Я — языковая модель, не могу продолжать.');

        $service = $this->makeService($ollama);
        $started = $service->start($this->user->id, 'PHP', 'Middle');

        $result = $service->generateReplySync($started['interview_id'], $this->user->id);

        $this->assertStringContainsString('собеседование', mb_strtolower($result['message']));
        $this->assertFalse(app(HrInterviewConductService::class)->breaksInterviewerRole($result['message']));
    }

    #[TestDox('JSON-вердикт в ответе модели завершает интервью')]
    public function test_process_assistant_response_completes_on_verdict_json(): void
    {
        $service = $this->makeService();
        $interview = HrInterview::create([
            'user_id'    => $this->user->id,
            'direction'  => 'PHP',
            'level'      => 'Middle',
            'status'     => 'active',
            'started_at' => now(),
        ]);

        $payload = '{"verdict":{"decision":"hire","summary":"Сильный кандидат","strengths":["PHP"],"weaknesses":[],"psycho_note":"ok","star_scores":{"situation":4,"task":4,"action":4,"result":4},"technical_level":"Middle","code_quality":"good"}}';

        $result = $this->invokeProcessAssistantResponse($service, $interview, $payload);

        $this->assertSame('hire', $result['verdict']['decision']);
        $this->assertSame('completed', $interview->fresh()->status);
    }

    #[TestDox('Маркер [CODE_TASK] извлекает стартовый код')]
    public function test_process_assistant_response_extracts_code_task(): void
    {
        $service = $this->makeService();
        $interview = HrInterview::create([
            'user_id'    => $this->user->id,
            'direction'  => 'PHP',
            'level'      => 'Middle',
            'status'     => 'active',
            'started_at' => now(),
        ]);

        $payload = <<<'MSG'
Исправьте баг в сервисе корзины.

```php
<?php
class Cart { public function total() { return 0; } }
```
[CODE_TASK]
MSG;

        $result = $this->invokeProcessAssistantResponse($service, $interview, $payload);

        $this->assertTrue($result['has_code_task']);
        $this->assertStringContainsString('class Cart', $result['code_starter'] ?? '');

        $this->assertDatabaseHas('hr_interview_messages', [
            'interview_id'  => $interview->id,
            'role'          => 'assistant',
            'has_code_task' => true,
        ]);
    }

    #[TestDox('submitCode прогоняет SonarQube и добавляет отчёт в сообщение')]
    public function test_submit_code_runs_sonar_and_appends_report_to_message(): void
    {
        $sonar = Mockery::mock(SonarQubeService::class);
        $sonar->shouldReceive('saveCodeSnippet')
            ->once()
            ->withArgs(fn (string $code, ?string $key, string $ext) => str_contains($code, 'array_sum') && $ext === 'php')
            ->andReturn('hr_unit_test');
        $sonar->shouldReceive('analyzeProject')
            ->once()
            ->with('hr_unit_test', 'php')
            ->andReturn([
                'success'     => true,
                'project_key' => 'hr_unit_test',
                'metrics'     => ['bugs' => '1', 'code_smells' => '2'],
                'issues'      => [
                    ['line' => 5, 'severity' => 'MAJOR', 'message' => 'Remove unused variable'],
                ],
            ]);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('isAvailable')->andReturn(true);
        $ollama->shouldReceive('chat')->andReturn('Расскажите, почему выбрали такой подход?');

        $service = $this->makeService($ollama, $sonar);
        $started = $service->start($this->user->id, 'PHP', 'Middle');

        $result = $service->submitCode(
            $started['interview_id'],
            $this->user->id,
            "<?php\nreturn array_sum([1,2,3]);",
        );

        $this->assertArrayHasKey('sonar', $result);
        $this->assertSame('1', $result['sonar']['metrics']['bugs'] ?? null);

        $userMessage = \App\Models\HrInterviewMessage::where('interview_id', $started['interview_id'])
            ->where('role', 'user')
            ->latest('id')
            ->first();
        $this->assertStringContainsString('[SONAR', $userMessage->content);
    }

    private function makeService(?OllamaService $ollama = null, ?SonarQubeService $sonar = null): AIHRService
    {
        $ollama ??= Mockery::mock(OllamaService::class);

        $kafka = Mockery::mock(HrInterviewKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->andReturn(false);

        if ($sonar === null) {
            $sonar = Mockery::mock(SonarQubeService::class);
            $sonar->shouldReceive('saveCodeSnippet')->andReturn('hr_mock');
            $sonar->shouldReceive('analyzeProject')->andReturn([
                'success'     => true,
                'project_key' => 'hr_mock',
                'metrics'     => [],
                'issues'      => [],
            ]);
        }

        return new AIHRService($ollama, $kafka, app(HrInterviewConductService::class), $sonar);
    }

    private function invokeProcessAssistantResponse(AIHRService $service, HrInterview $interview, string $payload): array
    {
        $method = new ReflectionMethod(AIHRService::class, 'processAssistantResponse');
        $method->setAccessible(true);

        return $method->invoke($service, $interview, $payload);
    }
}
