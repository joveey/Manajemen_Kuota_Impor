<?php

namespace App\Repositories\Sap;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository wrapper for SAP GR data.
 * Replace the table/select columns below to match the actual SAP view (MKPF/MSEG join, etc).
 */
class GrReceiptRepository
{
    private string $connection;
    private string $table;

    public function __construct()
    {
        $this->connection = config('services.sap.connection', 'sap');
        $this->table = config('services.sap.gr_receipts_table', 'sap_gr_receipts_view');
    }

    public function fetchByPeriod(Carbon $start, Carbon $end): Collection
    {
        return DB::connection($this->connection)
            ->table($this->table)
            ->select([
                'material_doc',
                'material_doc_year',
                'material_doc_item',
                'po_number',
                'po_line',
                'posting_date',
                'qty',
                'uom',
                'vendor_code',
                'vendor_name',
                'plant_code',
                'storage_location',
                'invoice_no',
                'category_code',
                'item_desc',
                'item_code',
            ])
            ->whereBetween('posting_date', [$start->toDateString(), $end->toDateString()])
            ->get();
    }
}
