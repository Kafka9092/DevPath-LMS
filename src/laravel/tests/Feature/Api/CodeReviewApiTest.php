<?php

namespace Tests\Feature\Api;

use App\Models\CodeReview;
use App\Models\User;
use App\Service\Kafka\CodeReviewKafkaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\ConfiguresCodeReviewTestEnvironment;
use Tests\Concerns\MocksCodeReviewDependencies;
use Tests\TestCase;

class CodeReviewApiTest extends TestCase
{
    use ConfiguresCodeReviewTestEnvironment;
    use MocksCodeReviewDependencies;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCodeReviewTestEnvironment();
        $this->user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);
    }

    private function apiToken(): string
    {
        return $this->user->createToken('vscode-test')->plainTextToken;
    }

    #[TestDox('POST /api/v1/tokens выдаёт Bearer-токен (REST)')]
    public function test_auth_token_issue(): void
    {
        $this->postJson('/api/v1/tokens', [
            'email'       => $this->user->email,
            'password'    => 'secret-password',
            'device_name' => 'vscode-test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
    }

    #[TestDox('POST /api/v1/code-reviews требует авторизацию')]
    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/code-reviews', [
            'code' => "<?php echo 1;\n",
        ])->assertUnauthorized();
    }

    #[TestDox('POST /api/v1/code-reviews: 201 и diagnostics для VS Code')]
    public function test_store_returns_rest_payload(): void
    {
        $this->mockCodeReviewPipeline();

        $this->withToken($this->apiToken())
            ->postJson('/api/v1/code-reviews', [
                'code'      => "<?php\nfunction add(int \$a, int \$b): int {\n    return \$a + \$b;\n}\n",
                'language'  => 'php',
                'filename'  => 'add.php',
                'file_path' => '/src/add.php',
            ])
            ->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.type', 'code-review')
            ->assertJsonPath('data.context.filename', 'add.php')
            ->assertJsonPath('meta.analysis_mode', 'sync')
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'overall_score',
                    'diagnostics',
                    'ai_evaluation',
                ],
            ]);

        $this->assertDatabaseCount('code_reviews', 1);
    }

    #[TestDox('GET /api/v1/code-reviews/{uuid} возвращает ресурс')]
    public function test_show_review_by_uuid(): void
    {
        $this->mockCodeReviewPipeline();

        $response = $this->withToken($this->apiToken())
            ->postJson('/api/v1/code-reviews', [
                'code' => "<?php\nclass X {}\n",
            ])
            ->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->withToken($this->apiToken())
            ->getJson('/api/v1/code-reviews/' . $uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.type', 'code-review');
    }

    #[TestDox('GET /api/v1/code-reviews возвращает коллекцию')]
    public function test_index_lists_reviews(): void
    {
        $this->mockCodeReviewPipeline();

        $this->withToken($this->apiToken())
            ->postJson('/api/v1/code-reviews', [
                'code' => "<?php echo 'hi';\n",
            ])
            ->assertCreated();

        $this->withToken($this->apiToken())
            ->getJson('/api/v1/code-reviews')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }

    #[TestDox('POST /api/v1/code-reviews async → 202 Accepted')]
    public function test_store_async_returns_accepted(): void
    {
        $this->mockCodeReviewKafkaAnalyze('550e8400-e29b-41d4-a716-446655440099');

        $this->withToken($this->apiToken())
            ->postJson('/api/v1/code-reviews', [
                'code' => "<?php echo 'hi';\n",
            ])
            ->assertAccepted()
            ->assertJsonPath('data.id', '550e8400-e29b-41d4-a716-446655440099')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['meta' => ['poll_url']]);
    }

    #[TestDox('Deprecated POST /api/v1/code-review/analyze всё ещё работает')]
    public function test_legacy_analyze_alias(): void
    {
        $this->mockCodeReviewPipeline();

        $this->withToken($this->apiToken())
            ->postJson('/api/v1/code-review/analyze', [
                'code' => "<?php return 1;\n",
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'code-review');
    }
}
