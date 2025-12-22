<?php

namespace App\Services;

use App\Models\PeriodSyncLog;
use Illuminate\Support\Carbon;

class PeriodSyncService
{
    public function syncPurchaseOrders(Carbon $start, Carbon $end): void
    {
        // TODO: integrate with SAP source query. For now we just record the sync timestamp.
        PeriodSyncLog::record('purchase_orders', $start, $end);
    }

    public function syncGoodsReceipts(Carbon $start, Carbon $end): void
    {
        PeriodSyncLog::record('gr_receipts', $start, $end);
    }
}
