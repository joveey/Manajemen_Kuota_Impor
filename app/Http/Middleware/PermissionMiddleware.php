<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!Auth::check()) {
            abort(401, 'Unauthorized - Please login first');
        }

        // Use Gate so that Admin bypass (Gate::before) also applies
        if (!Auth::user()->can($permission)) {
            abort(403, 'Forbidden - You do not have permission to access this resource');
        }

        return $next($request);
    }
}
