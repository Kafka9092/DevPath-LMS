<?php

namespace Tests\Feature;

use App\Models\CatAssessment;
use App\Models\Competence;
use App\Models\User;
use App\Service\Kafka\CatTestKafkaService;
use App\Service\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\BuildsCatTestSessions;
use Tests\Concerns\ConfiguresCatTestEnvironment;
use Tests\TestCase;

/**
 * Feature-тесты CAT: HTTP /test/* + Cache + (опционально) БД.
 */
class CatAdaptiveAssessmentTest extends TestCase
{
    use BuildsCatTestSessions;
    use ConfiguresCatTestEnvironment;
    use RefreshDatabase;

    private int $competenceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCatTestEnvironment();
        $this->mockCatAiDependencies();
        $this->competenceId = $this->seedCompetence()->id;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_show_redirects_to_create_course_without_direction(): void
    {
        $this->get('/test')
            ->assertRedirect(route('create.course'));
    }

    public function test_show_renders_test_page_with_direction(): void
    {
        $this->get('/test?direction=php')
            ->assertOk();
    }

    public function test_start_returns_session_and_first_question(): void
    {
        $response = $this->postJson('/test/start', ['direction' => 'php']);

        $response->assertOk()
            ->assertJsonStructure([
                'session_id',
                'status',
                'question' => ['id', 'text', 'options', 'correct'],
                'question_number',
                'is_finished',
            ])
            ->assertJsonPath('is_finished', false)
            ->assertJsonPath('question_number', 1);

        $sessionId = $response->json('session_id');
        $this->assertNotEmpty($sessionId);

        $this->getJson("/test/session/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('session_id', $sessionId);
    }

    public function test_session_missing_returns_error_payload(): void
    {
        $missingId = (string) Str::uuid();

        $this->getJson("/test/session/{$missingId}")
            ->assertOk()
            ->assertJson([
                'status'  => 'missing',
                'error'   => true,
                'message' => 'Сессия не найдена',
            ]);
    }

    public function test_submit_answer_validation_errors(): void
    {
        $this->postJson('/test/submit-answer', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session_id', 'question_id', 'answer']);
    }

    public function test_wrong_answer_via_http_resets_streak_and_continues_test(): void
    {
        $sessionId = $this->catSessionId();
        $this->seedCatSession($sessionId, $this->catBaseSession([
            'current_streak'         => 4,
            'current_question_level' => 'senior',
            'ceiling'                => 'senior',
            'display_level'          => 'senior',
            'confirmed_level'        => 'senior',
            'answers'                => $this->catAnswerSeries([
                ['senior', true],
                ['senior', true],
                ['senior', true],
                ['senior', true],
            ], $this->competenceId),
            'questions' => [
                $this->catQuestion(1, $this->competenceId),
                $this->catQuestion(2, $this->competenceId),
                $this->catQuestion(3, $this->competenceId),
                $this->catQuestion(4, $this->competenceId),
                $this->catQuestion(5, $this->competenceId),
            ],
        ]));

        $this->postJson('/test/submit-answer', [
            'session_id'  => $sessionId,
            'question_id' => 5,
            'answer'      => 2,
        ])
            ->assertOk()
            ->assertJsonPath('is_finished', false)
            ->assertJsonPath('is_correct', false)
            ->assertJsonPath('current_streak', 0);
    }

    public function test_solid_level_stops_test_via_http(): void
    {
        $sessionId = $this->catSessionId();
        $this->seedCatSession($sessionId, $this->catBaseSession([
            'current_streak'         => 4,
            'current_question_level' => 'senior',
            'ceiling'                => 'senior',
            'display_level'          => 'senior',
            'confirmed_level'        => 'senior',
            'answers'                => $this->catAnswerSeries([
                ['senior', true],
                ['senior', true],
                ['senior', true],
                ['senior', true],
            ], $this->competenceId),
            'questions' => [
                $this->catQuestion(1, $this->competenceId),
                $this->catQuestion(2, $this->competenceId),
                $this->catQuestion(3, $this->competenceId),
                $this->catQuestion(4, $this->competenceId),
                $this->catQuestion(5, $this->competenceId),
            ],
        ]));

        $this->postJson('/test/submit-answer', [
            'session_id'  => $sessionId,
            'question_id' => 5,
            'answer'      => 1,
        ])
            ->assertOk()
            ->assertJsonPath('is_finished', true)
            ->assertJsonPath('stop_reason', 'solid_level');
    }

    public function test_limit_stops_on_25th_answer_via_http(): void
    {
        $sessionId = $this->catSessionId();
        $answers = [];
        $questions = [];

        for ($i = 1; $i <= 24; $i++) {
            $answers[] = [
                'competence_id'    => $this->competenceId,
                'level'            => 'middle',
                'is_correct'       => true,
                'submitted_answer' => 1,
                'question_id'      => $i,
            ];
            $questions[] = $this->catQuestion($i, $this->competenceId);
        }
        $questions[] = $this->catQuestion(25, $this->competenceId);

        $this->seedCatSession($sessionId, $this->catBaseSession([
            'current_streak'         => 2,
            'current_question_level' => 'middle',
            'ceiling'                => 'senior',
            'display_level'          => 'middle',
            'answers'                => $answers,
            'questions'              => $questions,
        ]));

        $this->postJson('/test/submit-answer', [
            'session_id'  => $sessionId,
            'question_id' => 25,
            'answer'      => 1,
        ])
            ->assertOk()
            ->assertJsonPath('is_finished', true)
            ->assertJsonPath('stop_reason', 'limit');
    }

    public function test_advance_returns_next_question_after_generation(): void
    {
        $sessionId = $this->catSessionId();
        $this->seedCatSession($sessionId, $this->catBaseSession([
            'questions' => [
                $this->catQuestion(1, $this->competenceId),
                $this->catQuestion(2, $this->competenceId),
            ],
            'answers' => $this->catAnswerSeries([['junior', true]], $this->competenceId),
            'ui'      => ['awaiting_continue' => true, 'last_is_correct' => true],
        ]));

        $this->postJson('/test/advance', ['session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('is_finished', false)
            ->assertJsonStructure(['question' => ['id', 'text']])
            ->assertJsonPath('question_number', 2);
    }

    public function test_prepare_retake_clears_plan_session(): void
    {
        session([
            'course_plan_draft'   => ['direction' => 'PHP', 'from_test' => true],
            'cat_assessment_uuid' => (string) Str::uuid(),
        ]);

        $this->postJson('/test/prepare-retake')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull(session('course_plan_draft'));
        $this->assertNull(session('cat_assessment_uuid'));
    }

    public function test_finished_session_state_is_readable_via_get(): void
    {
        $sessionId = $this->catSessionId();
        $this->seedCatFinishedSession($sessionId, [
            'result' => [
                'overall_level'  => 'junior',
                'competencies' => ['Test Competence' => 100],
                'weak_topics'  => [],
                'strong_topics'=> ['Test Competence'],
            ],
            'is_correct'      => true,
            'assessment_uuid' => null,
            'direction'       => 'php',
            'practical_task'  => ['title' => 'Practice task', 'starter_code' => ''],
            'generation_status' => null,
        ]);

        $this->getJson("/test/session/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('is_finished', true)
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('practical_task.title', 'Practice task');
    }

    public function test_complete_task_marks_practical_skipped_in_database(): void
    {
        $user = User::factory()->create();
        $assessment = CatAssessment::create([
            'uuid'               => (string) Str::uuid(),
            'user_id'            => $user->id,
            'direction'          => 'PHP',
            'overall_level'      => 'junior',
            'stop_reason'        => 'solid_level',
            'questions_answered' => 5,
            'finished_at'        => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/test/complete-task', [
                'assessment_uuid' => $assessment->uuid,
                'skipped'         => true,
            ])
            ->assertOk()
            ->assertJsonPath('skipped', true)
            ->assertJsonPath('is_complete', true);

        $this->assertTrue($assessment->fresh()->practical_skipped);
    }

    public function test_authenticated_finish_persists_cat_assessment(): void
    {
        $user = User::factory()->create();
        $sessionId = $this->catSessionId();

        $this->seedCatSession($sessionId, $this->catBaseSession([
            'current_streak'         => 4,
            'current_question_level' => 'senior',
            'ceiling'                => 'senior',
            'display_level'          => 'senior',
            'confirmed_level'        => 'senior',
            'answers'                => $this->catAnswerSeries([
                ['senior', true],
                ['senior', true],
                ['senior', true],
                ['senior', true],
            ], $this->competenceId),
            'questions' => [
                $this->catQuestion(1, $this->competenceId),
                $this->catQuestion(2, $this->competenceId),
                $this->catQuestion(3, $this->competenceId),
                $this->catQuestion(4, $this->competenceId),
                $this->catQuestion(5, $this->competenceId),
            ],
        ]));

        $this->actingAs($user)
            ->postJson('/test/submit-answer', [
                'session_id'  => $sessionId,
                'question_id' => 5,
                'answer'      => 1,
            ])
            ->assertOk()
            ->assertJsonPath('assessment_saved', true)
            ->assertJsonPath('stop_reason', 'solid_level');

        $this->assertDatabaseHas('cat_assessments', [
            'user_id'       => $user->id,
            'overall_level' => 'senior',
            'direction'     => 'PHP',
            'stop_reason'   => 'solid_level',
        ]);
    }

    public function test_force_senior_preview_is_blocked_outside_local_environment(): void
    {
        $this->postJson('/test/force-senior-preview', ['direction' => 'PHP'])
            ->assertStatus(403);
    }

    private function mockCatAiDependencies(): void
    {
        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('isAvailable')->andReturn(true);
        $ollama->shouldReceive('generateJson')->andReturnUsing(function (string $prompt): array {
            if (str_contains($prompt, 'практическую задачу')) {
                return [
                    'task' => [
                        'title'        => 'HTTP practice task',
                        'description'  => 'Solve it',
                        'starter_code' => '// code',
                    ],
                ];
            }

            return [
                'type'    => 'single',
                'topic'   => 'test',
                'text'    => 'Следующий вопрос по HTTP?',
                'options' => ['A', 'B', 'C', 'D'],
                'correct' => [1],
            ];
        });

        $kafka = Mockery::mock(CatTestKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->andReturn(false);

        $this->instance(OllamaService::class, $ollama);
        $this->instance(CatTestKafkaService::class, $kafka);
    }

    private function seedCompetence(): Competence
    {
        return Competence::create([
            'name'           => 'Test Competence',
            'category'       => 'syntax',
            'beginner_level' => false,
            'junior_level'   => true,
            'middle_level'   => true,
            'senior_level'   => true,
        ]);
    }
}
