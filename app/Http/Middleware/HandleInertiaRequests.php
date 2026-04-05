<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [

            // Auth user (single source of truth)
            'auth' => [
                'user' => optional($request->user())->only(
                    'id',
                    'name',
                    'email',
                    'role',
                    'profile_photo_url',
                    'phone',
                    'email_verified_at'
                ),
            ],

            // Flash messages
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],

            // TEMP: Disabled notifications to prevent worker/db issues
            'appNotifications' => [],

        ]);
    }
}
