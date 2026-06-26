<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CodeReview;
use App\Service\CodeReviewApiPresenter;
use App\Service\CodeReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API ресурса code-reviews (анализ кода).
 *
 * POST   /api/v1/code-reviews          — создать проверку
 * GET    /api/v1/code-reviews          — список проверок
 * GET    /api/v1/code-reviews/latest   — последняя проверка
 * GET    /api/v1/code-reviews/{uuid}   — одна проверка
 */
class CodeReviewApiController extends Controller
{
    use RespondsWithJsonApi;

    public function __construct(
        protected CodeReviewService $codeReview,
        protected CodeReviewApiPresenter $presenter,
    ) {}

    /** POST /api/v1/code-reviews */
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
            $result = $this->codeReview->review(
                $data['code'],
                $request->user()->id,
                $context,
            );

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
                        'poll_url' => url('/api/v1/jobs/' . $jobId),
                    ],
                );
            }

            $payload = $this->presenter->present($result, $context, 'sync');
            $location = isset($payload['uuid'])
                ? url('/api/v1/code-reviews/' . $payload['uuid'])
                : null;

            return $this->created(
                $this->wrapReview($payload),
                $location,
                ['analysis_mode' => 'sync'],
            );
        } catch (\Throwable $e) {
            return $this->error('Не удалось выполнить проверку: ' . $e->getMessage(), 500);
        }
    }

    /** GET /api/v1/code-reviews */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page'         => ['nullable', 'integer', 'min:1'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:50'],
            'include_code' => ['nullable', 'boolean'],
        ]);

        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 20);
        $includeCode = (bool) ($data['include_code'] ?? false);

        $query = CodeReview::query()
            ->where('user_id', $request->user()->id)
            ->latest();

        $total = (clone $query)->count();
        $records = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(function (CodeReview $record) use ($includeCode): array {
                $formatted = $this->codeReview->formatRecord($record);

                return $this->wrapReview(
                    $this->presenter->presentHistoryItem($formatted, $includeCode),
                    'code-review-summary',
                );
            })
            ->values()
            ->all();

        return $this->ok($records, 200, [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /** GET /api/v1/code-reviews/latest */
    public function latest(Request $request): JsonResponse
    {
        $record = $this->codeReview->latestForUser($request->user()->id);

        if ($record === null) {
            return $this->ok(null);
        }

        return $this->ok(
            $this->wrapReview($this->presenter->present($record, [], 'sync')),
            200,
            ['analysis_mode' => 'sync'],
        );
    }

    /** GET /api/v1/code-reviews/{uuid} */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $record = CodeReview::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($record === null) {
            return $this->error('Проверка не найдена.', 404);
        }

        $formatted = $this->codeReview->formatRecord($record);

        return $this->ok(
            $this->wrapReview($this->presenter->present($formatted, [], 'sync')),
            200,
            ['analysis_mode' => 'sync'],
        );
    }

    /** @deprecated Используйте store() — POST /api/v1/code-reviews */
    public function analyze(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /** @deprecated Используйте index() — GET /api/v1/code-reviews */
    public function history(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /** @param  array<string, mixed>  $payload */
    private function wrapReview(array $payload, string $type = 'code-review'): array
    {
        return array_merge(['type' => $type], $payload);
    }
}
