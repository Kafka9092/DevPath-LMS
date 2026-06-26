<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Service\CodeReviewApiPresenter;
use App\Service\Kafka\KafkaJobResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REST: GET /api/v1/jobs/{id} — статус асинхронной проверки кода. */
class JobApiController extends Controller
{
    use RespondsWithJsonApi;

    public function __construct(
        protected KafkaJobResolver $jobs,
        protected CodeReviewApiPresenter $presenter,
    ) {}

    public function show(Request $request, string $jobId): JsonResponse
    {
        $request->validate([
            'filename'  => ['nullable', 'string', 'max:255'],
            'file_path' => ['nullable', 'string', 'max:1024'],
        ]);

        $result = $this->jobs->poll($jobId);
        $status = (string) ($result['status'] ?? 'pending');

        if ($status === 'pending') {
            return $this->ok([
                'type'   => 'job',
                'id'     => $jobId,
                'status' => 'pending',
            ]);
        }

        if ($status === 'failed') {
            return $this->error(
                (string) ($result['message'] ?? 'Задача завершилась с ошибкой.'),
                500,
            );
        }

        $context = [
            'filename'  => $request->query('filename'),
            'file_path' => $request->query('file_path'),
        ];

        unset($result['status']);

        $review = $this->presenter->present($result, $context, 'async');

        return $this->ok([
            'type'   => 'job',
            'id'     => $jobId,
            'status' => 'completed',
            'review' => array_merge(['type' => 'code-review'], $review),
        ], 200, [
            'analysis_mode' => 'async',
        ]);
    }
}
