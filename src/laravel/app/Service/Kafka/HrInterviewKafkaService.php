<?php

namespace App\Service\Kafka;

/** Kafka для AI HR-собеседования — ответы и реакция на код кандидата. */
class HrInterviewKafkaService extends AbstractKafkaJobService
{
    protected function configKey(): string
    {
        return 'hr_interview';
    }

    public function consumerGroup(): string
    {
        return (string) config('kafka.hr_interview.consumer_group');
    }
}
