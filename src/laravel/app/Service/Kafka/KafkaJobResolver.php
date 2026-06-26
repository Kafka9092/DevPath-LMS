<?php

namespace App\Service\Kafka;

/**
 * Один endpoint /kafka/job для всех модулей.
 * Фронт шлёт job_id — мы ищем результат то в cache уроков, то review, то HR.
 */
class KafkaJobResolver
{
    public function __construct(
        protected LessonKafkaService $lesson,
        protected CodeReviewKafkaService $codeReview,
        protected HrInterviewKafkaService $hrInterview,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function poll(string $jobId): array
    {
        // перебираем сервисы — у каждого свой префикс ключа в cache
        foreach ([$this->lesson, $this->codeReview, $this->hrInterview] as $service) {
            $result = $service->peekResult($jobId);

            if ($result === null) {
                continue;
            }

            $service->tryGetResult($jobId);

            if (! empty($result['error'])) {
                return array_merge($result, ['status' => 'failed']);
            }

            return array_merge($result, ['status' => 'ready']);
        }

        // consumer ещё не успел — фронт подождёт и спросит снова
        return ['status' => 'pending'];
    }
}
