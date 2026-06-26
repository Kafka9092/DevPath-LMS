<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** REST: токены доступа (Sanctum) для внешних клиентов. */
class AuthTokenController extends Controller
{
    use RespondsWithJsonApi;

    /** POST /api/v1/tokens */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        $token = $user->createToken($data['device_name']);

        return $this->created([
            'type'       => 'token',
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => $this->userPayload($user),
        ]);
    }

    /** GET /api/v1/me */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok($this->userPayload($user));
    }

    /** DELETE /api/v1/tokens/current */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'type'  => 'user',
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }
}
