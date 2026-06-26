<?php

namespace App\Service\Kafka;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Базовый класс для работы с Kafka в проекте.
 * От него наследуются сервисы уроков, теста, code review и HR.
 * Тут общая логика: отправить задачу, подождать результат в cache, сохранить результат.
 */
abstract class AbstractKafkaJobService
{
    // каждый наследник говорит, какая секция в config/kafka.php (lesson, cat_test и т.д.)
    abstract protected function configKey(): string;

    // включена ли вообще асинхронность для этого модуля (KAFKA_*_ASYNC=true)
    public function isEnabled(): bool
    {
        return (bool) config("kafka.{$this->configKey()}.async");
    }

    // можно ли для конкретной операции (content, analyze, reply...) уходить в Kafka
    public function useKafkaFor(string $operation): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return (bool) config("kafka.{$this->configKey()}.async_{$operation}", false);
    }

    public function brokers(): string
    {
        return (string) config('kafka.brokers');
    }

    // имя топика из конфига, например content_generate -> lesson.content.generate
    public function topic(string $key): string
    {
        return (string) config("kafka.{$this->configKey()}.topics.{$key}");
    }

    // сколько секунд ждать результат (у уроков разный timeout на content и chat)
    public function pollTimeout(?string $operation = null): float
    {
        if ($operation !== null) {
            $specific = config("kafka.{$this->configKey()}.poll_timeout_seconds.{$operation}");

            if ($specific !== null) {
                return (float) $specific;
            }
        }

        $default = config("kafka.{$this->configKey()}.poll_timeout_seconds");

        return (float) ($default ?? 25);
    }

    // ключ в Laravel cache, куда consumer кладёт готовый ответ по job_id
    public function resultCacheKey(string $jobId): string
    {
        return 'kafka_' . str_replace('.', '_', $this->configKey()) . '_result_' . $jobId;
    }

    /**
     * Отправка задачи в Kafka.
     * Генерируем job_id (uuid), кладём его в JSON — фронт потом по нему poll'ит.
     */
    public function publish(string $topic, array $payload, ?string $messageKey = null): string
    {
        $jobId = $payload['job_id'] ?? (string) \Illuminate\Support\Str::uuid();
        $payload['job_id'] = $jobId;

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $this->brokers());

        $producer = new \RdKafka\Producer($conf);

        $producerTopic = $producer->newTopic($topic);
        $producerTopic->produce(RD_KAFKA_PARTITION_UA, 0, $encoded, $messageKey ?? $jobId);
        $producer->poll(0);

        $flushResult = $producer->flush(5000);
        if ($flushResult !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            Log::warning('Kafka flush returned code: ' . $flushResult);
        }

        return $jobId;
    }

    // синхронное ожидание в PHP-процессе (используется редко, в основном poll с фронта)
    public function waitForResult(string $jobId, ?float $timeoutSeconds = null): ?array
    {
        $timeout = $timeoutSeconds ?? $this->pollTimeout();
        $key = $this->resultCacheKey($jobId);
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $res = Cache::get($key);
            if (is_array($res)) {
                Cache::forget($key);

                return $res;
            }
            usleep(200_000);
        }

        return null;
    }

    // посмотреть результат, не удаляя из cache (для KafkaJobResolver)
    public function peekResult(string $jobId): ?array
    {
        $res = Cache::get($this->resultCacheKey($jobId));

        return is_array($res) ? $res : null;
    }

    // забрать результат и удалить из cache (чтобы не отдавать дважды)
    public function tryGetResult(string $jobId): ?array
    {
        $res = Cache::get($this->resultCacheKey($jobId));
        if (! is_array($res)) {
            return null;
        }

        Cache::forget($this->resultCacheKey($jobId));

        return $res;
    }

    // consumer после обработки кладёт сюда ответ — фронт его заберёт через /kafka/job
    public function storeResult(string $jobId, array $result): void
    {
        Cache::put($this->resultCacheKey($jobId), $result, 120);
    }

    // publish + wait в одном вызове (fallback если нужно подождать прямо в API)
    public function publishAndWait(string $topic, array $payload, ?string $messageKey = null, ?string $operation = null): array
    {
        try {
            $jobId = $this->publish($topic, $payload, $messageKey);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => 'Kafka publish error: ' . $e->getMessage()];
        }

        $result = $this->waitForResult($jobId, $this->pollTimeout($operation));
        if ($result === null) {
            return ['error' => true, 'message' => 'Генерация через Kafka заняла слишком много времени. Попробуйте ещё раз.'];
        }

        return $result;
    }
}
