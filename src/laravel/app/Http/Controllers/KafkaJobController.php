<?php

namespace App\Http\Controllers;

use App\Service\Kafka\KafkaJobResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

//API для polling асинхронных AI-задач

class KafkaJobController extends Controller
{
    public function __construct(protected KafkaJobResolver $jobs) {}

    public function poll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_id' => ['required', 'uuid'],
        ]);

        return response()->json($this->jobs->poll($data['job_id']));
    }
}
