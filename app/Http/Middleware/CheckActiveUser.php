<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth::check() && !auth::user()->is_active) {
            Auth::logout();
            
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact administrator.']);
        }

        return $next($request);
    }
}