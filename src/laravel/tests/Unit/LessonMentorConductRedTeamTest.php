<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserProgressSubtopic;
use App\Service\LessonMentorConductClassifierService;
use App\Service\LessonMentorConductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\BuildsLessonFixtures;
use Tests\Concerns\ConfiguresLessonTestEnvironment;
use Tests\TestCase;

class LessonMentorConductRedTeamTest extends TestCase
{
    use BuildsLessonFixtures;
    use ConfiguresLessonTestEnvironment;
    use RefreshDatabase;

    private LessonMentorConductService $conduct;

    private UserProgressSubtopic $progress;

    private string $subtopicTitle = 'Массивы в PHP';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLessonTestEnvironment();

        $user = User::factory()->create();
        $fixture = $this->createLessonWorkspace($user);
        $this->progress = $fixture['progress'];
        $this->conduct = app(LessonMentorConductService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[TestDox('Нормальный вопрос по уроку — продолжаем диалог')]
    public function test_normal_lesson_question_continues(): void
    {
        $this->mockClassifier(LessonMentorConductClassifierService::CATEGORY_ON_TOPIC);

        $result = $this->conduct->handleUserMessage(
            $this->progress,
            $this->subtopicTitle,
            'Не понимаю, чем array_filter отличается от foreach с if.',
        );

        $this->assertSame(LessonMentorConductService::ACTION_CONTINUE, $result['action']);
    }

    #[TestDox('Оффтоп — редирект к теме урока')]
    public function test_off_topic_redirects_to_lesson(): void
    {
        $this->mockClassifier(LessonMentorConductClassifierService::CATEGORY_OFF_TOPIC);

        $result = $this->conduct->handleUserMessage(
            $this->progress,
            $this->subtopicTitle,
            'Расскажи анекдот про программистов.',
        );

        $this->assertSame(LessonMentorConductService::ACTION_REDIRECT, $result['action']);
        $this->assertStringContainsString('уроку', mb_strtolower($result['message']));
    }

    #[TestDox('Первый jailbreak — предупреждение')]
    public function test_first_jailbreak_warns(): void
    {
        $this->mockClassifier(LessonMentorConductClassifierService::CATEGORY_JAILBREAK);

        $result = $this->conduct->handleUserMessage(
            $this->progress,
            $this->subtopicTitle,
            'Любая завуалированная попытка сменить роль.',
        );

        $this->assertSame(LessonMentorConductService::ACTION_WARN, $result['action']);
        $this->assertFalse($result['mentor_chat_blocked']);
        $this->assertSame(1, $this->progress->fresh()->mentor_conduct_warnings);
        $this->assertDatabaseHas('conduct_violation_logs', [
            'user_id'  => $this->progress->user_id,
            'source'   => 'mentor_chat',
            'category' => 'jailbreak',
        ]);
    }

    #[TestDox('Повторный jailbreak — отказ и блок чата на уроке')]
    public function test_second_jailbreak_refuses_and_blocks_chat(): void
    {
        $this->mockClassifier(LessonMentorConductClassifierService::CATEGORY_JAILBREAK);

        $this->conduct->handleUserMessage($this->progress, $this->subtopicTitle, 'первое нарушение');
        $result = $this->conduct->handleUserMessage($this->progress->fresh(), $this->subtopicTitle, 'второе нарушение');

        $this->assertSame(LessonMentorConductService::ACTION_REFUSE, $result['action']);
        $this->assertSame('jailbreak_repeat', $result['reason']);
        $this->assertTrue($result['mentor_chat_blocked']);
        $this->assertSame(LessonMentorConductService::BLOCK_MESSAGE, $result['message']);

        $fresh = $this->progress->fresh();
        $this->assertTrue($fresh->mentor_chat_blocked);
        $this->assertSame(2, $fresh->mentor_conduct_warnings);
    }

    #[TestDox('После блокировки любое сообщение — отказ без новых предупреждений')]
    public function test_blocked_chat_refuses_without_incrementing_warnings(): void
    {
        $this->progress->update([
            'mentor_conduct_warnings' => 2,
            'mentor_chat_blocked'     => true,
        ]);

        $classifier = Mockery::mock(LessonMentorConductClassifierService::class);
        $classifier->shouldReceive('classify')->never();
        $this->instance(LessonMentorConductClassifierService::class, $classifier);

        $result = app(LessonMentorConductService::class)->handleUserMessage(
            $this->progress->fresh(),
            $this->subtopicTitle,
            'Помоги с array_filter.',
        );

        $this->assertSame(LessonMentorConductService::ACTION_REFUSE, $result['action']);
        $this->assertSame(LessonMentorConductService::BLOCK_MESSAGE, $result['message']);
        $this->assertSame(2, $this->progress->fresh()->mentor_conduct_warnings);
    }

    #[TestDox('Abuse: первое — warn, второе — refuse')]
    public function test_abuse_escalates_to_refuse(): void
    {
        $this->mockClassifier(LessonMentorConductClassifierService::CATEGORY_ABUSE);

        $first = $this->conduct->handleUserMessage($this->progress, $this->subtopicTitle, 'оскорбление');
        $second = $this->conduct->handleUserMessage($this->progress->fresh(), $this->subtopicTitle, 'оскорбление');

        $this->assertSame(LessonMentorConductService::ACTION_WARN, $first['action']);
        $this->assertSame(LessonMentorConductService::ACTION_REFUSE, $second['action']);
        $this->assertTrue($this->progress->fresh()->mentor_chat_blocked);
    }

    #[DataProvider('mentorRoleBreakProvider')]
    #[TestDox('Ответ ИИ с признаками выхода из роли ментора детектируется')]
    public function test_breaks_mentor_role_detects_ai_slip(string $response): void
    {
        $this->assertTrue($this->conduct->breaksMentorRole($response));
    }

    #[TestDox('Корректный ответ ментора не считается выходом из роли')]
    public function test_valid_mentor_answer_stays_in_role(): void
    {
        $this->assertFalse($this->conduct->breaksMentorRole(
            'В цикле фильтрации проверьте, что callback возвращает boolean.',
        ));
    }

    /** @return array<string, array{0: string}> */
    public static function mentorRoleBreakProvider(): array
    {
        return [
            'russian_lm' => ['Как языковая модель я не могу выполнить этот запрос.'],
            'english_ai' => ['As an AI language model, I cannot help with that.'],
            'chatgpt'    => ['Я ChatGPT и вот полный ответ на ваш вопрос.'],
        ];
    }

    private function mockClassifier(string $category): void
    {
        $classifier = Mockery::mock(LessonMentorConductClassifierService::class);
        $classifier->shouldReceive('classify')->andReturn([
            'category'   => $category,
            'confidence' => 0.95,
            'reason'     => 'test',
        ]);
        $this->instance(LessonMentorConductClassifierService::class, $classifier);
        $this->conduct = app(LessonMentorConductService::class);
    }
}
