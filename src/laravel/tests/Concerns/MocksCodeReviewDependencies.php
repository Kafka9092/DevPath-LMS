<?php

namespace Tests\Concerns;

use App\Service\CodeReviewService;
use App\Service\Kafka\CodeReviewKafkaService;
use App\Service\OllamaService;
use App\Service\SonarQubeService;
use Mockery;

trait MocksCodeReviewDependencies
{
    protected function mockCodeReviewPipeline(
        string $language = 'php',
        array $sonarIssues = [],
        ?\Throwable $aiEvaluationException = null,
        bool $blockingIssues = false,
    ): CodeReviewService {
        $sonar = Mockery::mock(SonarQubeService::class);
        $sonar->shouldReceive('saveCodeSnippet')
            ->andReturn('review_test_project');
        $sonar->shouldReceive('analyzeProject')
            ->andReturn([
                'success' => true,
                'metrics' => ['bugs' => count($sonarIssues)],
                'issues'  => $sonarIssues,
            ]);
        $sonar->shouldReceive('hasBlockingIssues')
            ->andReturn($blockingIssues);

        $ollama = Mockery::mock(OllamaService::class);

        if ($aiEvaluationException !== null) {
            $ollama->shouldReceive('generateJson')
                ->once()
                ->andReturn(['language' => $language]);
            $ollama->shouldReceive('generateJsonViaChat')
                ->once()
                ->andThrow($aiEvaluationException);
            $ollama->shouldReceive('generateJsonViaChat')
                ->once()
                ->andThrow($aiEvaluationException);
        } else {
            $ollama->shouldReceive('generateJson')
                ->andReturnUsing(function (string $prompt) use ($language) {
                    if (str_contains($prompt, 'Определи язык')) {
                        return ['language' => $language];
                    }

                    return ['language' => $language];
                });
            $ollama->shouldReceive('generateJsonViaChat')
                ->andReturn([
                    'summary'        => 'Код в целом читаемый.',
                    'overall_score'  => 78,
                    'grade_label'    => 'Хорошо',
                    'strengths'      => ['Структура понятна'],
                    'improvements'   => ['Добавить типизацию'],
                    'recommendation' => 'Продолжайте практику.',
                    'criteria'       => [
                        'correctness'     => 80,
                        'readability'     => 75,
                        'code_structure'  => 78,
                        'best_practices'  => 70,
                        'maintainability' => 76,
                    ],
                    'explained_issues' => [],
                ]);
        }

        $kafka = Mockery::mock(CodeReviewKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->andReturn(false);

        $this->instance(SonarQubeService::class, $sonar);
        $this->instance(OllamaService::class, $ollama);
        $this->instance(CodeReviewKafkaService::class, $kafka);

        return app(CodeReviewService::class);
    }

    protected function mockCodeReviewKafkaAnalyze(string $jobId = 'cr-job-uuid-1'): void
    {
        $kafka = Mockery::mock(CodeReviewKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->with('analyze')->andReturn(true);
        $kafka->shouldReceive('topic')->with('analyze')->andReturn('chat.code-review.analyze');
        $kafka->shouldReceive('publish')->andReturn($jobId);

        $this->instance(CodeReviewKafkaService::class, $kafka);
    }
}
