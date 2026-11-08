<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::guard('school')->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'You are not authorised to access this area.');
        }

        return $next($request);
    }
}
