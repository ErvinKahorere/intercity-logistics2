<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null)
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasVerifiedEmail') || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return redirect()->route($redirectToRoute ?: 'verification.notice');
    }
}
