<?php

namespace Tests\Feature;

use App\Models\CodeReview;
use App\Models\User;
use App\Service\Kafka\CodeReviewKafkaService;
use App\Service\OllamaService;
use App\Service\SonarQubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\ConfiguresCodeReviewTestEnvironment;
use Tests\Concerns\MocksCodeReviewDependencies;
use Tests\TestCase;

class CodeReviewAnalyzeTest extends TestCase
{
    use ConfiguresCodeReviewTestEnvironment;
    use MocksCodeReviewDependencies;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCodeReviewTestEnvironment();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[TestDox('GET /code-review открывается')]
    public function test_index_page_renders(): void
    {
        $this->get('/code-review')->assertOk();
    }

    #[TestDox('POST /code-review/analyze: валидация — code обязателен')]
    public function test_analyze_validation_requires_code(): void
    {
        $this->postJson('/code-review/analyze', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[TestDox('POST /code-review/analyze: валидный PHP — sync-ответ с оценкой')]
    public function test_analyze_valid_php_returns_sync_result(): void
    {
        $this->mockCodeReviewPipeline();

        $this->actingAs($this->user)
            ->postJson('/code-review/analyze', [
                'code' => "<?php\nfunction add(int \$a, int \$b): int {\n    return \$a + \$b;\n}\n",
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_code', true)
            ->assertJsonPath('detected_language', 'php')
            ->assertJsonPath('overall_score', 78);

        $this->assertDatabaseCount('code_reviews', 1);
    }

    #[TestDox('POST /code-review/analyze: обычный текст — is_code=false')]
    public function test_analyze_plain_text_returns_not_code(): void
    {
        $this->mockCodeReviewPipeline();

        $this->postJson('/code-review/analyze', [
            'code' => 'Это переписка, а не исходники.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_code', false);

        $this->assertSame(0, CodeReview::count());
    }

    #[TestDox('Kafka analyze: status generating_analyze и job_id')]
    public function test_analyze_with_kafka_returns_job_id(): void
    {
        $this->mockCodeReviewKafkaAnalyze('kafka-cr-99');
        $this->mockSonarAndOllamaForKafkaGate();

        $this->actingAs($this->user)
            ->postJson('/code-review/analyze', [
                'code' => "<?php echo 'hi';\n",
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'generating_analyze')
            ->assertJsonPath('job_id', 'kafka-cr-99');
    }

    #[TestDox('POST /api/sonar-stats/analyze — тот же контроллер analyze')]
    public function test_api_sonar_stats_analyze_endpoint(): void
    {
        $this->mockCodeReviewPipeline();

        $this->postJson('/api/sonar-stats/analyze', [
            'code' => "<?php\nreturn 42;\n",
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_code', true);
    }

    #[TestDox('Ошибка Sonar/Ollama — HTTP 500 с сообщением')]
    public function test_analyze_internal_error_returns_500(): void
    {
        $sonar = Mockery::mock(SonarQubeService::class);
        $sonar->shouldReceive('saveCodeSnippet')->andThrow(new \RuntimeException('sonar down'));

        $kafka = Mockery::mock(CodeReviewKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->andReturn(false);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('generateJson')->andReturn(['language' => 'php']);

        $this->instance(SonarQubeService::class, $sonar);
        $this->instance(CodeReviewKafkaService::class, $kafka);
        $this->instance(OllamaService::class, $ollama);

        $this->actingAs($this->user)
            ->postJson('/code-review/analyze', [
                'code' => "<?php\nclass X {}\n",
            ])
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    private function mockSonarAndOllamaForKafkaGate(): void
    {
        $sonar = Mockery::mock(SonarQubeService::class);
        $ollama = Mockery::mock(OllamaService::class);

        $this->instance(SonarQubeService::class, $sonar);
        $this->instance(OllamaService::class, $ollama);
    }
}
