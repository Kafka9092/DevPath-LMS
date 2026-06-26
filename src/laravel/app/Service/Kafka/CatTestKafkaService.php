<?php

namespace App\Service\Kafka;

/** Kafka для CAT-теста (входной адаптивный тест). Наследует общую логику publish/cache. */
class CatTestKafkaService extends AbstractKafkaJobService
{
    protected function configKey(): string
    {
        return 'cat_test';
    }

    public function pollTimeout(?string $operation = null): float
    {
        return (float) config('kafka.cat_test.poll_timeout_seconds', 25);
    }
}
