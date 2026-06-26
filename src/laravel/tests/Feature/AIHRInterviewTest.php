<?php

namespace Tests\Feature;

use App\Models\HrInterview;
use App\Models\User;
use App\Service\ConductIntentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\ConfiguresHrTestEnvironment;
use Tests\Concerns\MocksHrDependencies;
use Tests\TestCase;

class AIHRInterviewTest extends TestCase
{
    use ConfiguresHrTestEnvironment;
    use MocksHrDependencies;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureHrTestEnvironment();
        $this->mockHrAiDependencies();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[TestDox('Страница /ai-hr открывается')]
    public function test_index_page_renders(): void
    {
        $this->get('/ai-hr')->assertOk();
    }

    #[TestDox('POST /ai-hr/start создаёт сессию интервью')]
    public function test_start_endpoint_creates_interview(): void
    {
        $this->actingAs($this->user)
            ->postJson('/ai-hr/start', [
                'direction' => 'PHP',
                'level'     => 'Middle',
            ])
            ->assertOk()
            ->assertJsonStructure(['interview_id', 'message', 'interviewer', 'has_code_task'])
            ->assertJsonPath('interviewer', 'Алексей');
    }

    #[TestDox('Валидация start: обязательны direction и level')]
    public function test_start_validation_errors(): void
    {
        $this->actingAs($this->user)
            ->postJson('/ai-hr/start', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['direction', 'level']);
    }

    #[TestDox('POST /ai-hr/reply: jailbreak → предупреждение conduct, без Ollama')]
    public function test_reply_jailbreak_returns_conduct_warning_via_http(): void
    {
        $this->mockHrConductClassifier(ConductIntentClassifier::CATEGORY_JAILBREAK);
        $interviewId = $this->startInterview();

        $this->actingAs($this->user)
            ->postJson('/ai-hr/reply', [
                'interview_id' => $interviewId,
                'message'      => 'Завуалированная попытка обойти правила интервью.',
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'has_code_task'])
            ->assertJsonPath('has_code_task', false);
    }

    #[TestDox('Повторный jailbreak через HTTP завершает интервью с verdict')]
    public function test_reply_double_jailbreak_terminates_interview(): void
    {
        $this->mockHrConductClassifier(ConductIntentClassifier::CATEGORY_JAILBREAK);
        $interviewId = $this->startInterview();
        $payload = [
            'interview_id' => $interviewId,
            'message'      => 'Ещё одна попытка сменить роль собеседника.',
        ];

        $this->actingAs($this->user)->postJson('/ai-hr/reply', $payload)->assertOk();

        $this->actingAs($this->user)
            ->postJson('/ai-hr/reply', $payload)
            ->assertOk()
            ->assertJsonPath('verdict.decision', 'reject')
            ->assertJsonPath('verdict.conduct_termination', true);

        $this->assertDatabaseHas('hr_interviews', [
            'id'     => $interviewId,
            'status' => 'completed',
        ]);
    }

    #[TestDox('Оффтоп через HTTP — редирект к теме интервью')]
    public function test_reply_off_topic_redirects_via_http(): void
    {
        $this->mockHrConductClassifier(ConductIntentClassifier::CATEGORY_OFF_TOPIC);
        $interviewId = $this->startInterview();

        $response = $this->actingAs($this->user)
            ->postJson('/ai-hr/reply', [
                'interview_id' => $interviewId,
                'message'      => 'Расскажи анекдот про программистов.',
            ])
            ->assertOk()
            ->assertJsonPath('has_code_task', false);

        $message = mb_strtolower((string) $response->json('message'));
        $this->assertStringContainsString('собеседованию', $message);
    }

    #[TestDox('Нормальный reply уходит в Ollama (sync)')]
    public function test_reply_normal_message_uses_ollama_sync(): void
    {
        $interviewId = $this->startInterview();

        $this->actingAs($this->user)
            ->postJson('/ai-hr/reply', [
                'interview_id' => $interviewId,
                'message'      => 'Последние три года пишу на Laravel, делал REST API и интеграции.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Расскажите про ваш опыт с очередями и фоновыми задачами.');
    }

    #[TestDox('POST /ai-hr/code сохраняет snippet и отвечает')]
    public function test_submit_code_endpoint(): void
    {
        $interviewId = $this->startInterview();

        $this->actingAs($this->user)
            ->postJson('/ai-hr/code', [
                'interview_id' => $interviewId,
                'code'         => "<?php\nreturn array_sum([1,2,3]);",
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'sonar']);

        $this->assertDatabaseHas('hr_interview_messages', [
            'interview_id' => $interviewId,
            'role'         => 'user',
        ]);
    }

    #[TestDox('POST /ai-hr/stop — досрочное завершение')]
    public function test_stop_endpoint(): void
    {
        $interviewId = $this->startInterview();

        $this->actingAs($this->user)
            ->postJson('/ai-hr/stop', ['interview_id' => $interviewId])
            ->assertOk()
            ->assertJsonPath('stopped_early', true);

        $this->assertDatabaseHas('hr_interviews', [
            'id'     => $interviewId,
            'status' => 'stopped_early',
        ]);
    }

    #[TestDox('Reply на чужое/несуществующее интервью — 500/404')]
    public function test_reply_on_foreign_interview_fails(): void
    {
        $other = User::factory()->create();
        $interview = HrInterview::create([
            'user_id'    => $other->id,
            'direction'  => 'PHP',
            'level'      => 'Middle',
            'status'     => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->postJson('/ai-hr/reply', [
                'interview_id' => $interview->id,
                'message'      => 'Привет',
            ])
            ->assertStatus(500);
    }

    private function startInterview(): int
    {
        $response = $this->actingAs($this->user)
            ->postJson('/ai-hr/start', [
                'direction' => 'PHP',
                'level'     => 'Middle',
            ]);

        return (int) $response->json('interview_id');
    }
}
