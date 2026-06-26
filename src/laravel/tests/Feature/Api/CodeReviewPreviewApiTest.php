<?php

namespace Tests\Feature\Api;

use App\Models\CodeReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\ConfiguresCodeReviewTestEnvironment;
use Tests\Concerns\MocksCodeReviewDependencies;
use Tests\TestCase;

class CodeReviewPreviewApiTest extends TestCase
{
    use ConfiguresCodeReviewTestEnvironment;
    use MocksCodeReviewDependencies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCodeReviewTestEnvironment();
    }

    #[TestDox('POST /api/v1/analyze/preview — без auth, без записи в БД')]
    public function test_preview_does_not_persist(): void
    {
        $this->mockCodeReviewPipeline();

        $this->postJson('/api/v1/analyze/preview', [
            'code'     => "<?php\nfunction x() { return 1; }\n",
            'language' => 'php',
        ])
            ->assertOk()
            ->assertJsonPath('meta.persisted', false)
            ->assertJsonPath('data.is_code', true)
            ->assertJsonStructure(['data' => ['overall_score', 'ai_evaluation', 'diagnostics']]);

        $this->assertDatabaseCount('code_reviews', 0);
    }

    #[TestDox('POST /api/v1/analyze/preview — язык из имени файла, если редактор не распознал')]
    public function test_preview_detects_language_from_filename(): void
    {
        $this->mockCodeReviewPipeline();

        $this->postJson('/api/v1/analyze/preview', [
            'code'     => "namespace App\\Http\\Controllers;\n\nclass AuthController {\n    public function register() {}\n}\n",
            'language' => 'plaintext',
            'filename' => 'AuthController.php',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_code', true)
            ->assertJsonPath('data.detected_language', 'php')
            ->assertJsonStructure(['data' => ['overall_score', 'ai_evaluation']]);
    }
}
