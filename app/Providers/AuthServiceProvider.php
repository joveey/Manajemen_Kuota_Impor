<?php

namespace App\Providers;

use App\Models\Permission;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Admin bypass + global aliasing for create/read
        Gate::before(function ($user, $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            if (!is_string($ability)) {
                return null;
            }

            // Global create alias
            if (str_starts_with($ability, 'create ') || in_array($ability, ['po.create', 'product.create'], true)) {
                return $user->hasPermission('create') ? true : null;
            }

            // Global read alias
            if (str_starts_with($ability, 'read ')) {
                if ($user->hasPermission('read')) {
                    return true; // full read
                }
                if ($user->hasPermission('read limited')) {
                    $module = trim(substr($ability, strlen('read ')));
                    // allowed non-admin modules for limited read
                    $allowed = [
                        'dashboard', 'quota', 'purchase_orders', 'master_data', 'reports',
                    ];
                    return in_array($module, $allowed, true) ? true : false;
                }
            }

            return null; // defer to regular gates
        });

        // Register all permissions as gates
        try {
            foreach (Permission::all() as $permission) {
                Gate::define($permission->name, function ($user) use ($permission) {
                    return $user->hasPermission($permission->name);
                });
            }
        } catch (\Exception $e) {
            // Handle the case when migrations have not run yet (fresh installs)
        }
    }
}
