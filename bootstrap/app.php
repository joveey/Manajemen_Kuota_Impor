<?php

use App\Http\Middleware\CheckActiveUser;
use App\Http\Middleware\AuditLogMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ForbidRoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Register helper functions (safe to require multiple times thanks to guards)
require_once __DIR__.'/../app/Support/format.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'active.user' => CheckActiveUser::class,
            'forbid-role' => ForbidRoleMiddleware::class,
            'audit' => AuditLogMiddleware::class,
        ]);
        
        // Apply to web middleware group
        $middleware->web(append: [
            CheckActiveUser::class,
            AuditLogMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
