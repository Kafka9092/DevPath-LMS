<?php

namespace Tests\Concerns;

use App\Service\Kafka\LessonKafkaService;
use App\Service\LessonMentorConductClassifierService;
use App\Service\OllamaService;
use Mockery;

trait MocksLessonDependencies
{
    protected function mockMentorConductClassifier(
        string $category = LessonMentorConductClassifierService::CATEGORY_ON_TOPIC,
        float $confidence = 0.95,
        ?string $reason = 'test',
    ): void {
        $classifier = Mockery::mock(LessonMentorConductClassifierService::class);
        $classifier->shouldReceive('classify')->andReturn([
            'category'   => $category,
            'confidence' => $confidence,
            'reason'     => $reason ?? 'test',
        ]);
        $this->instance(LessonMentorConductClassifierService::class, $classifier);
    }

    protected function mockLessonOllama(?string $jsonContent = null): void
    {
        $this->mockMentorConductClassifier();

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('isAvailable')->andReturn(true);

        if ($jsonContent !== null) {
            $ollama->shouldReceive('generateJson')->andReturn(['content' => $jsonContent]);
        }

        $this->mockLessonKafkaOff();

        $this->instance(OllamaService::class, $ollama);
    }

    protected function mockLessonKafkaOff(): void
    {
        $kafka = Mockery::mock(LessonKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->andReturn(false);
        $kafka->shouldReceive('getContentJobId')->andReturn(null);
        $kafka->shouldReceive('peekResult')->andReturn(null);

        $this->instance(LessonKafkaService::class, $kafka);
    }

    protected function mockLessonKafkaChat(string $jobId = 'lesson-chat-job-1'): void
    {
        $kafka = Mockery::mock(LessonKafkaService::class);
        $kafka->shouldReceive('useKafkaFor')->with('chat')->andReturn(true);
        $kafka->shouldReceive('topic')->with('mentor_chat')->andReturn('lesson.mentor.chat');
        $kafka->shouldReceive('publish')->andReturn($jobId);

        $this->instance(LessonKafkaService::class, $kafka);
    }
}
