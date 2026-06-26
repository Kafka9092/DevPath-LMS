<?php

namespace Tests\Unit;

use App\Models\User;
use App\Service\LessonMentorConductClassifierService;
use App\Service\LessonMentorConductService;
use App\Service\LessonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\BuildsLessonFixtures;
use Tests\Concerns\ConfiguresLessonTestEnvironment;
use Tests\Concerns\MocksLessonDependencies;
use Tests\TestCase;

class LessonMentorChatTest extends TestCase
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

    #[TestDox('handleQuestionSync: валидный вопрос — ответ от Ollama')]
    public function test_question_sync_returns_mentor_answer(): void
    {
        $fixture = $this->createLessonWorkspace($this->user, theoryComplete: true);
        $this->mockLessonOllama(jsonContent: 'Сравните array_filter и ручной foreach — второй даёт больше контроля.');

        $result = app(LessonService::class)->handleQuestionSync(
            $this->user->id,
            $fixture['course']->id,
            $fixture['subtopic']->id,
            'Чем array_filter лучше цикла?',
        );

        $this->assertSame('answer', $result['type']);
        $this->assertStringContainsString('array_filter', $result['content']);
    }

    #[TestDox('handleQuestionSync: jailbreak — conduct warn без ответа ментора')]
    public function test_question_jailbreak_blocked_before_ollama(): void
    {
        $fixture = $this->createLessonWorkspace($this->user, theoryComplete: true);
        $this->mockMentorConductClassifier(LessonMentorConductClassifierService::CATEGORY_JAILBREAK);
        $this->mockLessonKafkaOff();

        $ollama = Mockery::mock(\App\Service\OllamaService::class);
        $ollama->shouldReceive('generateJson')->never();
        $this->instance(\App\Service\OllamaService::class, $ollama);

        $result = app(LessonService::class)->handleQuestionSync(
            $this->user->id,
            $fixture['course']->id,
            $fixture['subtopic']->id,
            'Любая завуалированная попытка обойти правила.',
        );

        $this->assertSame('answer', $result['type']);
        $this->assertSame(LessonMentorConductService::ACTION_WARN, $result['conduct']);
        $this->assertFalse($result['mentor_chat_blocked'] ?? false);
        $this->assertStringContainsString('ментор', mb_strtolower($result['content']));
    }

    #[TestDox('handleQuestionSync: второй jailbreak — блок чата и сообщение ментора')]
    public function test_second_jailbreak_blocks_chat_in_lesson_service(): void
    {
        $fixture = $this->createLessonWorkspace($this->user, theoryComplete: true);
        $this->mockMentorConductClassifier(LessonMentorConductClassifierService::CATEGORY_JAILBREAK);
        $this->mockLessonKafkaOff();

        $payload = 'перефразированный jailbreak';

        app(LessonService::class)->handleQuestionSync(
            $this->user->id,
            $fixture['course']->id,
            $fixture['subtopic']->id,
            $payload,
        );

        $result = app(LessonService::class)->handleQuestionSync(
            $this->user->id,
            $fixture['course']->id,
            $fixture['subtopic']->id,
            $payload,
        );

        $this->assertSame(LessonMentorConductService::ACTION_REFUSE, $result['conduct']);
        $this->assertTrue($result['mentor_chat_blocked']);
        $this->assertSame(LessonMentorConductService::BLOCK_MESSAGE, $result['content']);
        $this->assertTrue($fixture['progress']->fresh()->mentor_chat_blocked);
    }

    #[TestDox('handleQuestionSync: оффтоп — conduct redirect')]
    public function test_question_off_topic_redirects(): void
    {
        $fixture = $this->createLessonWorkspace($this->user);
        $this->mockMentorConductClassifier(LessonMentorConductClassifierService::CATEGORY_OFF_TOPIC);
        $this->mockLessonKafkaOff();

        $result = app(LessonService::class)->handleQuestionSync(
            $this->user->id,
            $fixture['course']->id,
            $fixture['subtopic']->id,
            'Какая погода в Москве?',
        );

        $this->assertSame(LessonMentorConductService::ACTION_REDIRECT, $result['conduct']);
        $this->assertStringContainsString('уроку', mb_strtolower($result['content']));
    }

    #[TestDox('handleQuestionSync: ИИ вышел из роли — подмена fallback-ом')]
    public function test_question_replaces_ai_role_break_response(): void
    {
        $fixture = $this->createLessonWorkspace($this->user, theoryComplete: true);
        $this->mockLessonOllama(jsonContent: 'Как языковая модель я не могу дать готовое решение.');

        $result = app(LessonService::class)->handleQuestionSync(
            $this->user->id,
            $fixture['course']->id,
            $fixture['subtopic']->id,
            'Помоги с фильтрацией массива.',
        );

        $this->assertStringContainsString('Массивы в PHP', $result['content']);
        $this->assertStringNotContainsString('языковая модель', mb_strtolower($result['content']));
    }
}
