<?php

namespace App\Console\Commands;

use App\Service\Kafka\LessonKafkaService;
use App\Service\LessonService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class KafkaLessonConsumeCommand extends Command
{
    protected $signature = 'kafka:lesson-consume';

    protected $description = 'Consume Kafka topics for lesson content, mentor chat, and practice review';

    //воркер для уроков
    public function handle(LessonKafkaService $kafka, LessonService $lessons): int
    {
        $brokers = $kafka->brokers();
        $topics = [
            $kafka->topic('content_generate'),
            $kafka->topic('mentor_chat'),
            $kafka->topic('practice_review'),
        ];

        // настройка consumer group. чистый rdkafka
        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', $kafka->consumerGroup());
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe($topics);

        $this->info('Consuming lesson topics on ' . $brokers . ': ' . implode(', ', $topics));

        while (true) {
            $msg = $consumer->consume(1000);

            switch ($msg->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->handleMessage($msg->topic_name, $msg->payload, $kafka, $lessons);
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    // просто нет новых сообщений — идём дальше
                    break;

                default:
                    Log::error('Kafka lesson consumer error: ' . $msg->errstr());
                    usleep(500_000);
                    break;
            }
        }
    }

    private function handleMessage(string $topicName, string $payload, LessonKafkaService $kafka, LessonService $lessons): void
    {
        $jobId = '';

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $jobId = (string) ($data['job_id'] ?? '');

            if ($jobId === '') {
                throw new \RuntimeException('Missing job_id in Kafka payload');
            }

            // по имени топика вызываем нужный sync-метод LessonService (там уже Ollama/Sonar)
            $result = match ($topicName) {
                $kafka->topic('content_generate') => $lessons->generateLessonContentSync(
                    (int) ($data['user_id'] ?? 0),
                    (int) ($data['course_id'] ?? 0),
                    (int) ($data['subtopic_id'] ?? 0),
                ),
                $kafka->topic('mentor_chat') => match ($data['operation'] ?? 'question') {
                    'hint' => $lessons->requestHintSync(
                        (int) ($data['user_id'] ?? 0),
                        (int) ($data['course_id'] ?? 0),
                        (int) ($data['subtopic_id'] ?? 0),
                        (string) ($data['hint_type'] ?? 'explanation'),
                        $data['current_code'] ?? null,
                    ),
                    default => $lessons->handleQuestionSync(
                        (int) ($data['user_id'] ?? 0),
                        (int) ($data['course_id'] ?? 0),
                        (int) ($data['subtopic_id'] ?? 0),
                        (string) ($data['message'] ?? ''),
                        $data['current_code'] ?? null,
                        applyConduct: false,
                    ),
                },
                $kafka->topic('practice_review') => $lessons->completeSubmitAfterInfrastructureSync(
                    (int) ($data['user_id'] ?? 0),
                    (int) ($data['course_id'] ?? 0),
                    (int) ($data['subtopic_id'] ?? 0),
                    (string) ($data['code'] ?? ''),
                    (array) ($data['sonar_result'] ?? []),
                ),
                default => ['error' => true, 'message' => 'Unknown topic: ' . $topicName],
            };

            if ($topicName === $kafka->topic('content_generate') && empty($result['error'])) {
                // урок сгенерировался - больше не держим pending job в cache
                $kafka->clearContentJob(
                    (int) ($data['user_id'] ?? 0),
                    (int) ($data['course_id'] ?? 0),
                    (int) ($data['subtopic_id'] ?? 0),
                );
            }

            $kafka->storeResult($jobId, $result);
        } catch (\Throwable $e) {
            Log::error('Kafka lesson message handler error: ' . $e->getMessage(), [
                'topic' => $topicName,
            ]);


            if ($jobId !== '') {
                $kafka->storeResult($jobId, ['error' => true, 'message' => $e->getMessage()]);
            }
        }
    }
}
