<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForbidRoleMiddleware
{
    /**
     * Block requests when the authenticated user has one of the forbidden roles.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user && !empty($roles)) {
            foreach ($roles as $role) {
                if ($user->hasRole(trim($role))) {
                    abort(Response::HTTP_FORBIDDEN);
                }
            }
        }

        return $next($request);
    }
}
