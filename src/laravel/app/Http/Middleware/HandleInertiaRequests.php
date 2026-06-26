<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    
    protected $rootView = 'app';

    
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? [
                        ...$user->only('id', 'name', 'email', 'email_verified_at'),
                        'avatar_url' => $user->avatar_url,
                        'roles' => $user->getRoleNames()->values()->all(),
                        'is_admin' => $user->isAdmin(),
                    ]
                    : null,
            ],
            'features' => [
                'adaptiveTestContinueBar' => (bool) config('services.features.adaptive_test_continue_bar', true),
            ],
        ];
    }
}
