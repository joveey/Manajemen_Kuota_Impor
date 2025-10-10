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
        if (!Auth::check()) {
            abort(401, 'Unauthorized - Please login first');
        }

        if (!Auth::user()->hasRole($roles)) {
            abort(403, 'Forbidden - You do not have the required role to access this resource');
        }

        return $next($request);
    }
}
