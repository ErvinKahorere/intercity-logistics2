<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (! $request->user()) {
            abort(403, 'Unauthorized');
        }

        $userRole = strtolower((string) $request->user()->role);
        $allowedRoles = array_map(fn ($role) => strtolower((string) $role), $roles);

        if (! in_array($userRole, $allowedRoles, true)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}

