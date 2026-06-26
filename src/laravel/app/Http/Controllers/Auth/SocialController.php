<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    private function oauthRedirectUrl(string $provider): string
    {
        $configured = config("services.{$provider}.redirect");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return rtrim(config('app.url'), '/') . "/auth/{$provider}/callback";
    }

    private function socialiteDriver(string $provider)
    {
        return Socialite::driver($provider)->redirectUrl($this->oauthRedirectUrl($provider));
    }

    public function redirectToProvider($provider)
    {
        if (!in_array($provider, ['github', 'google'])) {
            abort(404);
        }

        return $this->socialiteDriver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
    {
        if (!in_array($provider, ['github', 'google'])) {
            abort(404);
        }

        try {
            $socialUser = $this->socialiteDriver($provider)->user();
        } catch (\Exception $e) {
            Log::warning('OAuth callback failed', [
                'provider' => $provider,
                'message' => $e->getMessage(),
                'redirect' => $this->oauthRedirectUrl($provider),
            ]);

            $message = 'Ошибка авторизации через ' . $provider;

            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }

            return redirect('/login')->withErrors(['error' => $message]);
        }

        $user = User::where($provider . '_id', $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                $provider . '_id' => $socialUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email' => $socialUser->getEmail(),
                $provider . '_id' => $socialUser->getId(),
                'password' => null,
                'email_verified_at' => now(),
            ]);

            $user->assignDefaultUserRole();
        }

        Auth::login($user);

        return redirect()->intended('/main');
    }
}
