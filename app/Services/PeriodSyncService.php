<?php

namespace App\Services;

use App\Models\PeriodSyncLog;
use Illuminate\Support\Carbon;

class PeriodSyncService
{
    public function __construct(
        private readonly GrSapSyncService $grSapSyncService,
    ) {
    }

    public function syncPurchaseOrders(Carbon $start, Carbon $end): void
    {
        // TODO: integrate with SAP source query. For now we just record the sync timestamp.
        PeriodSyncLog::record('purchase_orders', $start, $end);
    }

    /**
     * Trigger SAP GR sync for the provided month range.
     *
     * @return array<string,mixed>
     */
    public function syncGoodsReceipts(Carbon $start, Carbon $end): array
    {
        $periodKey = $start->format('Y-m');

        return $this->grSapSyncService->sync($periodKey);
    }
}
