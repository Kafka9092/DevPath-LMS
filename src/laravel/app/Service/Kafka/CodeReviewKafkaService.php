<?php

namespace App\Service\Kafka;

/** Kafka только для страницы «Анализ кода» — один топик analyze. */
class CodeReviewKafkaService extends AbstractKafkaJobService
{
    protected function configKey(): string
    {
        return 'code_review';
    }

    public function consumerGroup(): string
    {
        return (string) config('kafka.code_review.consumer_group');
    }
}
