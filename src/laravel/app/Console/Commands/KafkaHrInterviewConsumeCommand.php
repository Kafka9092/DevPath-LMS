<?php

namespace App\Console\Commands;

use App\Service\AIHRService;
use App\Service\Kafka\HrInterviewKafkaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class KafkaHrInterviewConsumeCommand extends Command
{
    protected $signature = 'kafka:hr-interview-consume';

    protected $description = 'Consume Kafka topic for HR interview chat (reply and code submit)';

    // воркер для AI HR 
    public function handle(HrInterviewKafkaService $kafka, AIHRService $hrService): int
    {
        $brokers = $kafka->brokers();
        $topic   = $kafka->topic('chat');

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', $kafka->consumerGroup());
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe([$topic]);

        $this->info('Consuming HR interview topic on ' . $brokers . ': ' . $topic);

        while (true) {
            $msg = $consumer->consume(1000);

            switch ($msg->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->handleMessage($msg->payload, $kafka, $hrService);
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;

                default:
                    Log::error('Kafka HR interview consumer error: ' . $msg->errstr());
                    usleep(500_000);
                    break;
            }
        }
    }

    private function handleMessage(string $payload, HrInterviewKafkaService $kafka, AIHRService $hrService): void
    {
        $jobId = '';

        try {
            $data  = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $jobId = (string) ($data['job_id'] ?? '');

            if ($jobId === '') {
                throw new \RuntimeException('Missing job_id in Kafka payload');
            }

            // сообщение пользователя уже в БД — тут только ответ Ollama
            $result = $hrService->generateReplySync(
                (int) ($data['interview_id'] ?? 0),
                isset($data['user_id']) ? (int) $data['user_id'] : null,
                isset($data['user_message']) ? (string) $data['user_message'] : null,
            );

            $kafka->storeResult($jobId, array_merge($result, ['success' => empty($result['error'])]));
        } catch (\Throwable $e) {
            Log::error('Kafka HR interview message handler error: ' . $e->getMessage());

            if ($jobId !== '') {
                $kafka->storeResult($jobId, [
                    'error'   => true,
                    'message' => $e->getMessage(),
                    'success' => false,
                ]);
            }
        }
    }
}
