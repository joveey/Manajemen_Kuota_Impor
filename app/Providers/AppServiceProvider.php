<?php

namespace App\Providers;

use Illuminate\Database\Connectors\SqlServerConnector;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override the default SQL Server connector to drop ATTR_STRINGIFY_FETCHES
        // which the installed pdo_sqlsrv driver rejects.
        DB::extend('sqlsrv', function (array $config, string $name) {
            $config['name'] = $name;

            $connector = new SqlServerConnector();
            $connector->setDefaultOptions([
                PDO::ATTR_CASE => PDO::CASE_NATURAL,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $pdo = $connector->connect($config);

            return new SqlServerConnection(
                $pdo,
                $config['database'],
                $config['prefix'] ?? '',
                $config
            );
        });
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
