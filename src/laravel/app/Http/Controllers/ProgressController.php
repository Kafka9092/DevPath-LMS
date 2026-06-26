<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Service\ProgressStatsService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** Страница «Прогресс» — данные готовит ProgressStatsService. */
class ProgressController extends Controller
{
    public function __construct(
        protected ProgressStatsService $stats,
    ) {}

    public function index(): Response
    {
        $payload = $this->stats->build($this->resolveUserId());

        return Inertia::render('Progress', $payload);
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
