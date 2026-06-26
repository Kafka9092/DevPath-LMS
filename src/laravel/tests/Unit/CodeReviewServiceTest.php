<?php

namespace Tests\Unit;

use App\Models\CodeReview;
use App\Models\User;
use App\Service\CodeReviewService;
use App\Service\Kafka\CodeReviewKafkaService;
use App\Service\OllamaService;
use App\Service\SonarQubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionMethod;
use RuntimeException;
use Tests\Concerns\ConfiguresCodeReviewTestEnvironment;
use Tests\Concerns\MocksCodeReviewDependencies;
use Tests\TestCase;

class CodeReviewServiceTest extends TestCase
{
    use ConfiguresCodeReviewTestEnvironment;
    use MocksCodeReviewDependencies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureCodeReviewTestEnvironment();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[TestDox('looksLikeCode: PHP-фрагмент распознаётся как код')]
    public function test_looks_like_code_recognizes_php(): void
    {
        $service = $this->mockCodeReviewPipeline();
        $code = "<?php\nfunction sum(\$a, \$b) {\n    return \$a + \$b;\n}";

        $this->assertTrue($this->invokeLooksLikeCode($service, $code));
    }

    #[TestDox('looksLikeCode: обычный русский текст — не код')]
    public function test_looks_like_code_rejects_plain_text(): void
    {
        $service = $this->mockCodeReviewPipeline();

        $this->assertFalse($this->invokeLooksLikeCode(
            $service,
            'Привет! Это просто сообщение без программирования.',
        ));
    }

    #[TestDox('looksLikeCode: пустая строка — не код')]
    public function test_looks_like_code_rejects_empty_string(): void
    {
        $service = $this->mockCodeReviewPipeline();

        $this->assertFalse($this->invokeLooksLikeCode($service, '   '));
    }

    #[TestDox('review(): не-код возвращает is_code=false без Sonar')]
    public function test_review_returns_not_code_response_for_plain_text(): void
    {
        $service = $this->mockCodeReviewPipeline();

        $result = $service->review('Напиши мне отчёт по диплому завтра утром.', null);

        $this->assertFalse($result['is_code']);
        $this->assertSame('unknown', $result['detected_language']);
        $this->assertArrayHasKey('not_code_message', $result);
        $this->assertSame(0, CodeReview::count());
    }

    #[TestDox('detectLanguage: Ollama возвращает поддерживаемый язык')]
    public function test_detect_language_uses_ollama_json(): void
    {
        $service = $this->mockCodeReviewPipeline('python');

        $language = $service->detectLanguage("def hello():\n    print('hi')");

        $this->assertSame('python', $language);
    }

    #[TestDox('detectLanguage: при падении Ollama — эвристика PHP')]
    public function test_detect_language_falls_back_to_heuristic(): void
    {
        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('generateJson')
            ->once()
            ->andThrow(new RuntimeException('ollama down'));

        $kafka = Mockery::mock(CodeReviewKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->andReturn(false);

        $this->instance(OllamaService::class, $ollama);
        $this->instance(SonarQubeService::class, Mockery::mock(SonarQubeService::class));
        $this->instance(CodeReviewKafkaService::class, $kafka);

        $service = app(CodeReviewService::class);
        $language = $service->detectLanguage('<?php echo 1;');

        $this->assertSame('php', $language);
    }

    #[TestDox('reviewSync: успешный пайплайн Sonar + Ollama')]
    public function test_review_sync_returns_full_result(): void
    {
        $service = $this->mockCodeReviewPipeline();
        $code = "<?php\nreturn array_sum([1, 2, 3]);";

        $result = $service->reviewSync($code, null);

        $this->assertTrue($result['is_code']);
        $this->assertSame('php', $result['detected_language']);
        $this->assertSame('php', $result['editor_language']);
        $this->assertSame(78, $result['overall_score']);
        $this->assertArrayHasKey('ai_evaluation', $result);
        $this->assertFalse($result['saved']);
    }

    #[TestDox('reviewSync: с user_id сохраняет запись в code_reviews')]
    public function test_review_sync_persists_record_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $service = $this->mockCodeReviewPipeline();
        $code = "<?php\nclass Demo {}\n";

        $result = $service->reviewSync($code, $user->id);

        $this->assertTrue($result['saved']);
        $this->assertDatabaseHas('code_reviews', [
            'uuid'              => $result['uuid'],
            'user_id'           => $user->id,
            'detected_language' => 'php',
            'overall_score'     => 78,
        ]);
    }

    #[TestDox('fallbackEvaluation: BLOCKER от Sonar → score ≤ 45 при недоступном Ollama')]
    public function test_fallback_evaluation_caps_score_on_blocking_issues(): void
    {
        $blockingIssue = [
            'severity' => 'BLOCKER',
            'type'     => 'VULNERABILITY',
            'message'  => 'Remove usage of eval',
            'line'     => 2,
        ];

        $service = $this->mockCodeReviewPipeline(
            language: 'php',
            sonarIssues: [$blockingIssue],
            aiEvaluationException: new RuntimeException('ollama unavailable'),
            blockingIssues: true,
        );

        $code = "<?php\neval(\$_POST['cmd']);\n";
        $result = $service->reviewSync($code, null);

        $this->assertLessThanOrEqual(45, $result['overall_score']);
        $this->assertStringContainsString('доработки', $result['ai_evaluation']['grade_label']);
    }

    private function invokeLooksLikeCode(CodeReviewService $service, string $code): bool
    {
        $method = new ReflectionMethod(CodeReviewService::class, 'looksLikeCode');
        $method->setAccessible(true);

        return (bool) $method->invoke($service, $code);
    }
}
