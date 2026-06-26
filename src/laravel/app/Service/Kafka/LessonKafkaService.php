<?php

namespace App\Service\Kafka;

use Illuminate\Support\Facades\Cache;

/**
 * Kafka-обёртка для модуля уроков (workspace).
 * Три топика: генерация контента, чат с ментором, проверка практики.
 */
class LessonKafkaService extends AbstractKafkaJobService
{
    protected function configKey(): string
    {
        return 'lesson';
    }

    public function consumerGroup(): string
    {
        return (string) config('kafka.lesson.consumer_group');
    }

    // ключ чтобы не слать две генерации одного урока параллельно
    public function contentPendingKey(int $userId, int $courseId, int $subtopicId): string
    {
        return "lesson_content_pending:{$userId}:{$courseId}:{$subtopicId}";
    }

    public function rememberContentJob(int $userId, int $courseId, int $subtopicId, string $jobId): void
    {
        Cache::put($this->contentPendingKey($userId, $courseId, $subtopicId), $jobId, 600);
    }

    public function getContentJobId(int $userId, int $courseId, int $subtopicId): ?string
    {
        $jobId = Cache::get($this->contentPendingKey($userId, $courseId, $subtopicId));

        return is_string($jobId) && $jobId !== '' ? $jobId : null;
    }

    public function clearContentJob(int $userId, int $courseId, int $subtopicId): void
    {
        Cache::forget($this->contentPendingKey($userId, $courseId, $subtopicId));
    }
}
