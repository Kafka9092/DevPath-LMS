<?php

namespace Tests\Concerns;

use App\Service\ConductIntentClassifier;
use App\Service\HrInterviewConductClassifierService;
use App\Service\Kafka\HrInterviewKafkaService;
use App\Service\OllamaService;
use App\Service\SonarQubeService;
use Mockery;

trait MocksHrDependencies
{
    protected function mockHrConductClassifier(
        string $category = ConductIntentClassifier::CATEGORY_ON_TOPIC,
        float $confidence = 0.95,
        ?string $reason = 'test',
    ): void {
        $classifier = Mockery::mock(HrInterviewConductClassifierService::class);
        $classifier->shouldReceive('classify')->andReturn([
            'category'   => $category,
            'confidence' => $confidence,
            'reason'     => $reason ?? 'test',
        ]);
        $this->instance(HrInterviewConductClassifierService::class, $classifier);
    }

    protected function mockHrAiDependencies(
        string $conductCategory = ConductIntentClassifier::CATEGORY_ON_TOPIC,
    ): void {
        $this->mockHrConductClassifier($conductCategory);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('isAvailable')->andReturn(true);
        $ollama->shouldReceive('chat')->andReturn(
            'Расскажите про ваш опыт с очередями и фоновыми задачами.',
        );

        $kafka = Mockery::mock(HrInterviewKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->andReturn(false);

        $this->instance(OllamaService::class, $ollama);
        $this->instance(HrInterviewKafkaService::class, $kafka);
        $this->mockHrSonar();
    }

    protected function mockHrSonar(array $issues = []): void
    {
        $sonar = Mockery::mock(SonarQubeService::class);
        $sonar->shouldReceive('saveCodeSnippet')->andReturn('hr_test_project');
        $sonar->shouldReceive('analyzeProject')->andReturn([
            'success'     => true,
            'project_key' => 'hr_test_project',
            'metrics'     => ['bugs' => '0', 'code_smells' => (string) count($issues)],
            'issues'      => $issues,
        ]);

        $this->instance(SonarQubeService::class, $sonar);
    }
}
