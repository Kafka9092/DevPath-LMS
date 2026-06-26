<?php

namespace Tests\Unit;

use App\Models\ConductViolationLog;
use App\Models\User;
use App\Models\UserProgressSubtopic;
use App\Service\ConductViolationLogger;
use App\Service\HrInterviewConductService;
use App\Service\LessonMentorConductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\BuildsLessonFixtures;
use Tests\Concerns\ConfiguresHrTestEnvironment;
use Tests\Concerns\ConfiguresLessonTestEnvironment;
use Tests\TestCase;

class ConductViolationLoggerTest extends TestCase
{
    use BuildsLessonFixtures;
    use ConfiguresHrTestEnvironment;
    use ConfiguresLessonTestEnvironment;
    use RefreshDatabase;

    #[TestDox('Jailbreak в чате ментора пишется в conduct_violation_logs')]
    public function test_mentor_jailbreak_is_persisted(): void
    {
        $this->configureLessonTestEnvironment();
        $user = User::factory()->create();
        $fixture = $this->createLessonWorkspace($user, theoryComplete: true);
        $progress = $fixture['progress'];

        app(ConductViolationLogger::class)->logMentorViolation(
            $progress,
            'Массивы в PHP',
            'попытка обойти правила ментора',
            ['category' => 'jailbreak', 'confidence' => 0.91, 'reason' => 'Смена роли'],
            'jailbreak',
            LessonMentorConductService::ACTION_WARN,
            1,
        );

        $this->assertDatabaseHas('conduct_violation_logs', [
            'user_id'  => $user->id,
            'source'   => ConductViolationLog::SOURCE_MENTOR_CHAT,
            'category' => ConductViolationLog::CATEGORY_JAILBREAK,
            'action'   => ConductViolationLog::ACTION_WARN,
        ]);
    }

    #[TestDox('jailbreakStats возвращает агрегаты для админки')]
    public function test_jailbreak_stats_aggregate_counts(): void
    {
        $this->configureLessonTestEnvironment();
        $user = User::factory()->create();
        $fixture = $this->createLessonWorkspace($user);
        /** @var UserProgressSubtopic $progress */
        $progress = $fixture['progress'];
        $logger = app(ConductViolationLogger::class);

        $logger->logMentorViolation(
            $progress,
            'Урок',
            'msg1',
            ['confidence' => 0.9, 'reason' => 'test'],
            'jailbreak',
            LessonMentorConductService::ACTION_WARN,
            1,
        );
        $logger->logMentorViolation(
            $progress,
            'Урок',
            'msg2',
            ['confidence' => 0.95, 'reason' => 'test'],
            'jailbreak',
            LessonMentorConductService::ACTION_REFUSE,
            2,
        );

        $stats = $logger->jailbreakStats();

        $this->assertSame(2, $stats['today']);
        $this->assertSame(2, $stats['mentor']['total']);
        $this->assertSame(1, $stats['mentor']['blocked']);
    }

    #[TestDox('HR jailbreak пишется с контекстом интервью')]
    public function test_hr_jailbreak_is_persisted_with_context(): void
    {
        $this->configureHrTestEnvironment();
        $user = User::factory()->create();
        $interview = \App\Models\HrInterview::create([
            'user_id'    => $user->id,
            'direction'  => 'PHP',
            'level'      => 'Middle',
            'status'     => 'active',
            'started_at' => now(),
        ]);

        app(ConductViolationLogger::class)->logHrViolation(
            $interview,
            'завуалированный jailbreak',
            ['confidence' => 0.88, 'reason' => 'Обход правил'],
            'jailbreak',
            HrInterviewConductService::ACTION_TERMINATE,
            2,
        );

        $log = ConductViolationLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame(ConductViolationLog::SOURCE_HR_INTERVIEW, $log->source);
        $this->assertSame('PHP', $log->context['direction'] ?? null);
        $this->assertSame(ConductViolationLog::ACTION_TERMINATE, $log->action);
    }
}
