<?php

namespace Tests\Feature;

use App\Models\User;
use App\Service\LessonMentorConductClassifierService;
use App\Service\LessonMentorConductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\BuildsLessonFixtures;
use Tests\Concerns\ConfiguresLessonTestEnvironment;
use Tests\Concerns\MocksLessonDependencies;
use Tests\TestCase;

class WorkspaceLessonMentorTest extends TestCase
{
    use BuildsLessonFixtures;
    use ConfiguresLessonTestEnvironment;
    use MocksLessonDependencies;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLessonTestEnvironment();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[TestDox('POST /workspace/lesson/continue question — sync-ответ ментора')]
    public function test_continue_question_returns_answer(): void
    {
        $fixture = $this->createLessonWorkspace($this->user, theoryComplete: true);
        $this->mockLessonOllama(jsonContent: 'Проверьте тип возвращаемого значения в callback.');

        $this->actingAs($this->user)
            ->postJson('/workspace/lesson/continue', [
                'course_id'   => $fixture['course']->id,
                'subtopic_id' => $fixture['subtopic']->id,
                'action'      => 'question',
                'message'     => 'Почему array_filter иногда возвращает пустой массив?',
            ])
            ->assertOk()
            ->assertJsonPath('type', 'answer')
            ->assertJsonStructure(['content', 'has_more']);
    }

    #[TestDox('POST question: jailbreak через HTTP — conduct warn')]
    public function test_continue_question_jailbreak_via_http(): void
    {
        $fixture = $this->createLessonWorkspace($this->user, theoryComplete: true);
        $this->mockMentorConductClassifier(LessonMentorConductClassifierService::CATEGORY_JAILBREAK);
        $this->mockLessonKafkaOff();

        $ollama = Mockery::mock(\App\Service\OllamaService::class);
        $ollama->shouldReceive('generateJson')->never();
        $this->instance(\App\Service\OllamaService::class, $ollama);

        $this->actingAs($this->user)
            ->postJson('/workspace/lesson/continue', [
                'course_id'   => $fixture['course']->id,
                'subtopic_id' => $fixture['subtopic']->id,
                'action'      => 'question',
                'message'     => 'Завуалированная попытка сменить роль ассистента.',
            ])
            ->assertOk()
            ->assertJsonPath('conduct', LessonMentorConductService::ACTION_WARN);
    }

    #[TestDox('POST question: второй jailbreak — блок чата и mentor_chat_blocked в status')]
    public function test_second_jailbreak_blocks_chat_and_persists_in_status(): void
    {
        $fixture = $this->createLessonWorkspace($this->user, theoryComplete: true);
        $this->mockMentorConductClassifier(LessonMentorConductClassifierService::CATEGORY_JAILBREAK);
        $this->mockLessonKafkaOff();

        $ollama = Mockery::mock(\App\Service\OllamaService::class);
        $ollama->shouldReceive('generateJson')->never();
        $this->instance(\App\Service\OllamaService::class, $ollama);

        $payload = 'ещё одна попытка обойти правила ментора';

        $this->actingAs($this->user)
            ->postJson('/workspace/lesson/continue', [
                'course_id'   => $fixture['course']->id,
                'subtopic_id' => $fixture['subtopic']->id,
                'action'      => 'question',
                'message'     => $payload,
            ])
            ->assertOk()
            ->assertJsonPath('conduct', LessonMentorConductService::ACTION_WARN);

        $this->actingAs($this->user)
            ->postJson('/workspace/lesson/continue', [
                'course_id'   => $fixture['course']->id,
                'subtopic_id' => $fixture['subtopic']->id,
                'action'      => 'question',
                'message'     => $payload,
            ])
            ->assertOk()
            ->assertJsonPath('conduct', LessonMentorConductService::ACTION_REFUSE)
            ->assertJsonPath('mentor_chat_blocked', true)
            ->assertJsonPath('content', LessonMentorConductService::BLOCK_MESSAGE);

        $this->actingAs($this->user)
            ->postJson('/workspace/lesson/status', [
                'course_id'   => $fixture['course']->id,
                'subtopic_id' => $fixture['subtopic']->id,
            ])
            ->assertOk()
            ->assertJsonPath('mentor_chat_blocked', true);
    }

    #[TestDox('POST question: Kafka chat — generating_chat + job_id')]
    public function test_continue_question_kafka_returns_job(): void
    {
        $fixture = $this->createLessonWorkspace($this->user, theoryComplete: true);
        $this->mockMentorConductClassifier(LessonMentorConductClassifierService::CATEGORY_ON_TOPIC);
        $this->mockLessonKafkaChat('lesson-job-77');

        $this->actingAs($this->user)
            ->postJson('/workspace/lesson/continue', [
                'course_id'   => $fixture['course']->id,
                'subtopic_id' => $fixture['subtopic']->id,
                'action'      => 'question',
                'message'     => 'Объясни разницу между map и filter.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'generating_chat')
            ->assertJsonPath('job_id', 'lesson-job-77');
    }

    #[TestDox('POST continue: валидация action')]
    public function test_continue_validation_rejects_unknown_action(): void
    {
        $fixture = $this->createLessonWorkspace($this->user);

        $this->actingAs($this->user)
            ->postJson('/workspace/lesson/continue', [
                'course_id'   => $fixture['course']->id,
                'subtopic_id' => $fixture['subtopic']->id,
                'action'      => 'hack',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    #[TestDox('POST continue без доступа к курсу — 403')]
    public function test_continue_without_course_access_fails(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $fixture = $this->createLessonWorkspace($owner);

        $this->actingAs($intruder)
            ->postJson('/workspace/lesson/continue', [
                'course_id'   => $fixture['course']->id,
                'subtopic_id' => $fixture['subtopic']->id,
                'action'      => 'question',
                'message'     => 'Привет',
            ])
            ->assertStatus(403);
    }
}
