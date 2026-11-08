<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('school');
        $user = $guard->user();

        if (! $user || ! (bool) $user->is_active) {
            $guard->logout();

            abort(403, 'Your account is inactive. Contact your school administrator.');
        }

        return $next($request);
    }
}
