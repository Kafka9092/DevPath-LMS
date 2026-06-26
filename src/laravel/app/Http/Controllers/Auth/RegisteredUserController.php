<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'turnstileSiteKey' => config('services.turnstile.site_key', ''),
        ]);
    }

    
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'captcha_token' => ['required', 'string', new Turnstile],
        ], [
            'password.confirmed' => 'Пароли не совпадают.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => config('mail.auto_verify') ? now() : null,
        ]);

        $user->assignDefaultUserRole();

        event(new Registered($user));

        Auth::login($user);

        if (config('mail.auto_verify')) {
            return redirect()->route('main');
        }

        return redirect()->route('verification.notice');
    }
}
