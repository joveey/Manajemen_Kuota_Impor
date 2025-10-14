<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 pagination views to match admin layout
        Paginator::useBootstrapFive();

        // Update last_login_at whenever a user authenticates
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $user = $event->user;
            if (method_exists($user, 'forceFill')) {
                $user->forceFill(['last_login_at' => now()])->save();
            }
        });
    }
}
