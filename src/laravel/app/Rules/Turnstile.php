<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret');

        if ($secret === null || $secret === '') {
            if (app()->environment('local', 'testing')) {
                return;
            }

            $fail('Проверка капчи не настроена.');

            return;
        }

        if (! is_string($value) || trim($value) === '') {
            $fail('Пройдите проверку капчи.');

            return;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post(self::VERIFY_URL, [
                'secret' => $secret,
                'response' => $value,
            ]);

        if (! $response->ok() || ! $response->json('success')) {
            $fail('Роботам вход воспрещён (капча не пройдена).');
        }
    }
}
