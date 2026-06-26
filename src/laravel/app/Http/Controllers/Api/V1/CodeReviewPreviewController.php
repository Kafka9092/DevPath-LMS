<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Service\CodeReviewApiPresenter;
use App\Service\CodeReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stateless-анализ кода для IDE-плагинов (без записи в БД, без авторизации).
 */
class CodeReviewPreviewController extends Controller
{
    use RespondsWithJsonApi;

    public function __construct(
        protected CodeReviewService $codeReview,
        protected CodeReviewApiPresenter $presenter,
    ) {}

    /** POST /api/v1/analyze/preview */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => ['required', 'string', 'min:1', 'max:200000'],
            'language'  => ['nullable', 'string', 'max:32'],
            'filename'  => ['nullable', 'string', 'max:255'],
            'file_path' => ['nullable', 'string', 'max:1024'],
        ]);

        $context = [
            'language'  => $data['language'] ?? null,
            'filename'  => $data['filename'] ?? null,
            'file_path' => $data['file_path'] ?? null,
        ];

        try {
            $result = $this->codeReview->reviewPreview($data['code'], $context);

            if (($result['status'] ?? null) === 'generating_analyze') {
                $jobId = (string) $result['job_id'];

                return $this->accepted(
                    [
                        'type'          => 'job',
                        'id'            => $jobId,
                        'status'        => 'pending',
                        'analysis_mode' => 'async',
                        'context'       => $context,
                    ],
                    [
                        'persisted' => false,
                        'poll_url'  => url('/api/v1/jobs/' . $jobId),
                    ],
                );
            }

            return $this->ok(
                array_merge(
                    ['type' => 'code-review-preview'],
                    $this->presenter->present($result, $context, 'sync'),
                ),
                200,
                [
                    'persisted'     => false,
                    'analysis_mode' => 'sync',
                ],
            );
        } catch (\Throwable $e) {
            return $this->error('Не удалось выполнить проверку: ' . $e->getMessage(), 500);
        }
    }
}
