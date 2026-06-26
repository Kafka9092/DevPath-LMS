<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

trait RespondsWithJsonApi
{
    /** @param  array<string, mixed>|null  $data */
    protected function ok(mixed $data = null, int $status = 200, array $meta = [], array $headers = []): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status, $headers);
    }

    /** @param  array<string, mixed>|null  $data */
    protected function created(mixed $data, ?string $location = null, array $meta = []): JsonResponse
    {
        $headers = $location ? ['Location' => $location] : [];

        return $this->ok($data, 201, $meta, $headers);
    }

    /** @param  array<string, mixed>|null  $data */
    protected function accepted(mixed $data, array $meta = []): JsonResponse
    {
        return $this->ok($data, 202, $meta);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
