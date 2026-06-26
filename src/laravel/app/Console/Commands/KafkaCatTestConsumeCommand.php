<?php

namespace App\Console\Commands;

use App\Service\Kafka\CatTestKafkaService;
use App\Service\TestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class KafkaCatTestConsumeCommand extends Command
{
    protected $signature = 'kafka:cat-test-consume';

    protected $description = 'Consume Kafka topics for adaptive CAT test (start, next question, finish task)';

    /** Worker для входного адаптивного теста — 3 топика на старт, следующий вопрос и практика. */
    public function handle(CatTestKafkaService $kafka, TestService $tests): int
    {
        $brokers = $kafka->brokers();
        $topics  = [
            $kafka->topic('start'),
            $kafka->topic('next_question'),
            $kafka->topic('finish'),
        ];

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', (string) config('kafka.cat_test.consumer_group'));
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe($topics);

        $this->info('Consuming CAT test topics on ' . $brokers . ': ' . implode(', ', $topics));

        while (true) {
            $msg = $consumer->consume(1000);

            switch ($msg->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->handleMessage($msg->topic_name, $msg->payload, $kafka, $tests);
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;

                default:
                    Log::error('Kafka CAT consumer error: ' . $msg->errstr());
                    usleep(500_000);
                    break;
            }
        }
    }

    private function handleMessage(string $topicName, string $payload, CatTestKafkaService $kafka, TestService $tests): void
    {
        $jobId = '';

        try {
            $data  = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $jobId = (string) ($data['job_id'] ?? '');

            if ($jobId === '') {
                throw new \RuntimeException('Missing job_id in Kafka payload');
            }

            // три разных операции CAT — start / next / финальная практика
            $result = match ($topicName) {
                $kafka->topic('start') => $tests->startAdaptiveTestSync(
                    (string) ($data['direction'] ?? ''),
                    (string) ($data['session_id'] ?? ''),
                ),
                $kafka->topic('next_question') => $tests->generateNextQuestionFromKafka(
                    (string) ($data['session_id'] ?? ''),
                ),
                $kafka->topic('finish') => $tests->generatePracticalTaskFromKafka(
                    (string) ($data['session_id'] ?? ''),
                    (string) ($data['direction'] ?? ''),
                    (string) ($data['final_level'] ?? 'junior'),
                ),
                default => ['error' => true, 'message' => 'Unknown topic: ' . $topicName],
            };

            $kafka->storeResult($jobId, $result);
        } catch (\Throwable $e) {
            Log::error('Kafka CAT message handler error: ' . $e->getMessage(), [
                'topic' => $topicName,
            ]);

            if (!empty($jobId)) {
                $kafka->storeResult($jobId, ['error' => true, 'message' => $e->getMessage()]);
            }
        }
    }
}
