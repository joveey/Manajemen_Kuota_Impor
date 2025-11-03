<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            app('router')->pushMiddlewareToGroup('web', \App\Http\Middleware\AuditLogMiddleware::class);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

