<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Service\CodeReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** Страница Code Review + POST /analyze. */
class CodeReviewController extends Controller
{
    public function __construct(
        protected CodeReviewService $codeReview,
    ) {}

    public function index(): Response
    {
        return Inertia::render('CodeReview');
    }

    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|min:1',
        ]);

        $code = $request->input('code');

        try {
            $result = $this->codeReview->review($code, $this->resolveUserId());

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Не удалось выполнить проверку: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolveUserId(): ?int
    {
        if ($id = Auth::id()) {
            return $id;
        }

        if (app()->environment('local')) {
            return User::query()->value('id');
        }

        return null;
    }
}
