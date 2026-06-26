<?php

namespace App\Console\Commands;

use App\Service\CodeReviewService;
use App\Service\Kafka\CodeReviewKafkaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class KafkaCodeReviewConsumeCommand extends Command
{
    protected $signature = 'kafka:code-review-consume';

    protected $description = 'Consume Kafka topic for code review analysis';

    // воркер для Code Review
    public function handle(CodeReviewKafkaService $kafka, CodeReviewService $codeReview): int
    {
        $brokers = $kafka->brokers();
        $topic   = $kafka->topic('analyze');

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', $kafka->consumerGroup());
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe([$topic]);

        $this->info('Consuming code review topic on ' . $brokers . ': ' . $topic);

        while (true) {
            $msg = $consumer->consume(1000);

            switch ($msg->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->handleMessage($msg->payload, $kafka, $codeReview);
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;

                default:
                    Log::error('Kafka code review consumer error: ' . $msg->errstr());
                    usleep(500_000);
                    break;
            }
        }
    }

    private function handleMessage(string $payload, CodeReviewKafkaService $kafka, CodeReviewService $codeReview): void
    {
        $jobId = '';

        try {
            $data  = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $jobId = (string) ($data['job_id'] ?? '');

            if ($jobId === '') {
                throw new \RuntimeException('Missing job_id in Kafka payload');
            }
            $persist = array_key_exists('persist', $data) ? (bool) $data['persist'] : true;

            $result = $codeReview->reviewSync(
                (string) ($data['code'] ?? ''),
                isset($data['user_id']) ? (int) $data['user_id'] : null,
                is_array($data['context'] ?? null) ? $data['context'] : [],
                persist: $persist,
            );

            $kafka->storeResult($jobId, array_merge($result, ['success' => empty($result['error'])]));
        } catch (\Throwable $e) {
            Log::error('Kafka code review message handler error: ' . $e->getMessage());

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
