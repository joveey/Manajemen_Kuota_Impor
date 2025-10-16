<?php

namespace App\Providers;

use App\Models\Quota;
use App\Observers\QuotaObserver;
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Quota::observe(QuotaObserver::class);
    }
    protected $listen = [
        \App\Events\QuotaImportCompleted::class => [
            \App\Listeners\RunProductQuotaAutoMapping::class,
        ],
    ];
}

