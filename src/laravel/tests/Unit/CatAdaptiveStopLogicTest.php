<?php

namespace Tests\Unit;

use App\Models\Competence;
use App\Service\CatAssessmentService;
use App\Service\Kafka\CatTestKafkaService;
use App\Service\LanguageCompetencePromptBuilder;
use App\Service\OllamaService;
use App\Service\TestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Concerns\BuildsCatTestSessions;
use Tests\Concerns\ConfiguresCatTestEnvironment;
use Tests\TestCase;

/**
 * Граничные значения и логика остановки CAT (без Ollama/Kafka в рантайме).
 */
class CatAdaptiveStopLogicTest extends TestCase
{
    use BuildsCatTestSessions;
    use ConfiguresCatTestEnvironment;
    use RefreshDatabase;

    private TestService $service;

    private int $competenceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCatTestEnvironment();
        $this->competenceId = $this->seedCompetence()->id;
        $this->service = $this->makeTestService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pattern_four_correct_one_wrong_does_not_trigger_solid_level_stop(): void
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
            'questions' => $this->catQuestions(5),
        ]));

        $result = $this->service->submitAdaptiveAnswer($sessionId, 5, 2);

        $this->assertFalse($result['is_finished'] ?? true);
        $this->assertSame(0, $result['current_streak'] ?? -1);
        $this->assertArrayNotHasKey('stop_reason', $result);
    }

    public function test_solid_level_stops_after_five_correct_at_ceiling(): void
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
            'questions' => $this->catQuestions(5),
        ]));

        $result = $this->service->submitAdaptiveAnswer($sessionId, 5, 1);

        $this->assertTrue($result['is_finished']);
        $this->assertSame('solid_level', $result['stop_reason']);
    }

    public function test_solid_level_does_not_stop_when_last_five_answers_are_on_different_levels(): void
    {
        $sessionId = $this->catSessionId();
        $this->seedCatSession($sessionId, $this->catBaseSession([
            'current_streak'         => 4,
            'current_question_level' => 'senior',
            'ceiling'                => 'senior',
            'display_level'          => 'senior',
            'answers'                => $this->catAnswerSeries([
                ['middle', true],
                ['middle', true],
                ['senior', true],
                ['senior', true],
            ], $this->competenceId),
            'questions' => $this->catQuestions(5),
        ]));

        $result = $this->service->submitAdaptiveAnswer($sessionId, 5, 1);

        $this->assertFalse($result['is_finished'] ?? true);
        $this->assertArrayNotHasKey('stop_reason', $result);
    }

    public function test_limit_stops_exactly_on_25th_answer(): void
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

        $result = $this->service->submitAdaptiveAnswer($sessionId, 25, 1);

        $this->assertTrue($result['is_finished']);
        $this->assertSame('limit', $result['stop_reason']);
    }

    public function test_confirmed_beginner_after_three_beginner_fails(): void
    {
        $sessionId = $this->catSessionId();
        $this->seedCatSession($sessionId, $this->catBaseSession([
            'current_streak'         => 0,
            'current_question_level' => 'beginner',
            'ceiling'                => 'beginner',
            'display_level'          => 'beginner',
            'beginner_fails'         => 2,
            'answers'                => $this->catAnswerSeries([
                ['beginner', false],
                ['beginner', false],
            ], $this->competenceId),
            'questions' => $this->catQuestions(3),
        ]));

        $result = $this->service->submitAdaptiveAnswer($sessionId, 3, 2);

        $this->assertTrue($result['is_finished']);
        $this->assertSame('confirmed_beginner', $result['stop_reason']);
    }

    #[DataProvider('evaluateAnswerBoundaryProvider')]
    public function test_evaluate_answer_boundaries(array $correct, mixed $answer, bool $expected): void
    {
        $question = array_merge($this->catQuestion(1, $this->competenceId), [
            'correct' => $correct,
            'type'    => count($correct) > 1 ? 'multiple' : 'single',
        ]);

        $this->assertSame($expected, $this->invokeEvaluateAnswer($question, $answer));
    }

    public static function evaluateAnswerBoundaryProvider(): array
    {
        return [
            'single correct index'        => [[1], 1, true],
            'single wrong index'          => [[1], 0, false],
            'dont know sentinel'          => [[1], -1, false],
            'multiple exact match'        => [[1, 3], [1, 3], true],
            'multiple missing one'        => [[1, 3], [1], false],
            'multiple extra wrong option' => [[1, 3], [1, 2, 3], false],
            'multiple order independent'  => [[1, 3], [3, 1], true],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function catQuestions(int $count): array
    {
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $questions[] = $this->catQuestion($i, $this->competenceId);
        }

        return $questions;
    }

    private function invokeEvaluateAnswer(array $question, mixed $answer): bool
    {
        $method = new ReflectionMethod(TestService::class, 'evaluateAnswer');
        $method->setAccessible(true);

        return $method->invoke($this->service, $question, $answer);
    }

    private function makeTestService(): TestService
    {
        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('isAvailable')->andReturn(true);
        $ollama->shouldReceive('generateJson')->andReturn([
            'type'    => 'single',
            'topic'   => 'test',
            'text'    => 'Следующий вопрос?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct' => [1],
        ]);

        $kafka = Mockery::mock(CatTestKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->andReturn(false);

        $cat = Mockery::mock(CatAssessmentService::class);
        $cat->shouldReceive('persistFromCatSession')->andReturn('00000000-0000-4000-8000-000000000001');

        return new TestService($ollama, $cat, $kafka, new LanguageCompetencePromptBuilder());
    }

    private function seedCompetence(): Competence
    {
        return Competence::create([
            'name'           => 'Unit Test Competence',
            'category'       => 'syntax',
            'beginner_level' => true,
            'junior_level'   => true,
            'middle_level'   => true,
            'senior_level'   => true,
        ]);
    }
}
