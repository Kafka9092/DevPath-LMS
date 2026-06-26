<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Service\AIHRService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** AI HR чат — start/reply/submitCode/stop. */
class AIHRController extends Controller
{
    public function __construct(protected AIHRService $hrService) {}

    public function index(): Response
    {
        return Inertia::render('AIHRChat');
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'string', 'max:50'],
            'level'     => ['required', 'in:Junior,Middle,Senior'],
        ]);

        try {
            $result = $this->hrService->start(
                userId:    $this->resolveUserId(),
                direction: $data['direction'],
                level:     $data['level'],
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'interview_id' => ['required', 'integer'],
            'message'      => ['required', 'string', 'max:4000'],
        ]);

        try {
            $result = $this->hrService->reply(
                interviewId: $data['interview_id'],
                userId:      $this->resolveUserId(),
                userMessage: $data['message'],
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function submitCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'interview_id' => ['required', 'integer'],
            'code'         => ['required', 'string', 'max:20000'],
        ]);

        try {
            $result = $this->hrService->submitCode(
                interviewId: $data['interview_id'],
                userId:        $this->resolveUserId(),
                code:          $data['code'],
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function stop(Request $request): JsonResponse
    {
        $data = $request->validate([
            'interview_id' => ['required', 'integer'],
        ]);

        try {
            $result = $this->hrService->stop(
                interviewId: $data['interview_id'],
                userId:      $this->resolveUserId(),
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
