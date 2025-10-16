<?php

namespace App\Listeners;

use App\Events\QuotaImportCompleted;
use App\Services\ProductQuotaAutoMapper;
use Illuminate\Support\Facades\Log;

class RunProductQuotaAutoMapping
{
    public function handle(QuotaImportCompleted $event): void
    {
        $summary = app(ProductQuotaAutoMapper::class)->runForPeriod($event->periodKey);
        Log::info(sprintf(
            'AutoMapping period=%s mapped=%d unmapped=%d total=%d',
            (string)$event->periodKey,
            $summary['mapped'] ?? 0,
            $summary['unmapped'] ?? 0,
            $summary['total_products'] ?? 0
        ));
    }
}

