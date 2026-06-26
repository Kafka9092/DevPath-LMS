<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Service\CatAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SeniorProgramController extends Controller
{
    public function hub(Request $request, CatAssessmentService $catAssessments): Response|\Illuminate\Http\RedirectResponse
    {
        $draft = session('course_plan_draft', []);
        $requestedDirection = strtoupper($request->query('direction', $draft['direction'] ?? 'PHP'));

        $seniorProgram = null;

        if (strtolower((string) ($draft['real_level'] ?? '')) === 'senior') {
            $seniorProgram = [
                'direction' => strtoupper((string) ($draft['direction'] ?? $requestedDirection)),
            ];
        } else {
            $userId = $this->resolveUserId();
            if ($userId) {
                $seniorProgram = $catAssessments->ensureSeniorPlanDraft($userId, $requestedDirection)
                    ?? $catAssessments->ensureSeniorPlanDraft($userId);
            }
        }

        if (!$seniorProgram) {
            return redirect()->route('plan.settings', ['direction' => $requestedDirection]);
        }

        return Inertia::render('SeniorHub', [
            'direction' => $seniorProgram['direction'],
            'realLevel' => 'senior',
        ]);
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
