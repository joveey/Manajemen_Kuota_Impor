<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\DbExpression;

class PoHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number','po_date','supplier','currency','note','published_at','created_by'
    ];

    protected $casts = [
        'po_date' => 'date',
        'published_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(PoLine::class, 'po_header_id');
    }

    /**
     * Scope: attach aggregated metrics per PO number using PostgreSQL-native functions.
     *
     * - Uses STRING_AGG, GREATEST, and normalized GR receipts as the source of truth for received quantity.
     * - Returns one row per po_number with summary columns and a status_key.
     */
    public function scopeWithAggregates($query)
    {
        $hasVendorNumber    = Schema::hasColumn('po_headers', 'vendor_number');
        $hasAmount          = Schema::hasColumn('po_lines', 'amount');
        $hasQtyOrdered      = Schema::hasColumn('po_lines', 'qty_ordered');
        $hasSapStatus       = Schema::hasColumn('po_lines', 'sap_order_status');
        $hasQtyToInvoice    = Schema::hasColumn('po_lines', 'qty_to_invoice');
        $hasQtyToDeliver    = Schema::hasColumn('po_lines', 'qty_to_deliver');
        $hasStorageLocation = Schema::hasColumn('po_lines', 'storage_location');
        $driver = DB::connection()->getDriverName();

        $qtyOrderedExprBase   = $hasQtyOrdered ? 'COALESCE(pl.qty_ordered,0)' : '0';
        $sumQtyOrderedExpr    = "SUM($qtyOrderedExprBase)";
        $sumQtyToInvoiceExpr  = $hasQtyToInvoice ? 'SUM(COALESCE(pl.qty_to_invoice,0))' : 'NULL';
        $sumQtyToDeliverExpr  = $hasQtyToDeliver ? 'SUM(COALESCE(pl.qty_to_deliver,0))' : 'NULL';
        $storagesExpr         = $hasStorageLocation ? DbExpression::stringAgg("NULLIF(pl.storage_location,'')", ', ', true) : 'NULL';
        $sumOutstandingExpr   = "GREATEST(SUM($qtyOrderedExprBase) - COALESCE(SUM(grn.qty),0), 0)";

        $statusExpr = sprintf(
            "CASE WHEN %s >= %s AND %s > 0 THEN '%s' WHEN %s > 0 THEN '%s' ELSE '%s' END",
            'COALESCE(SUM(grn.qty),0)',
            $sumQtyOrderedExpr,
            $sumQtyOrderedExpr,
            PurchaseOrder::STATUS_COMPLETED,
            'COALESCE(SUM(grn.qty),0)',
            PurchaseOrder::STATUS_PARTIAL,
            PurchaseOrder::STATUS_ORDERED
        );

        $grn = DB::table('gr_receipts')
            ->selectRaw("po_no, ".DbExpression::lineNoInt('line_no')." AS ln")
            ->selectRaw('SUM(qty) as qty')
            ->groupBy('po_no', 'ln');

        return $query
            ->from('po_headers as ph')
            ->leftJoin('po_lines as pl', 'pl.po_header_id', '=', 'ph.id')
            ->leftJoinSub($grn, 'grn', function ($j) {
                $j->on('grn.po_no', '=', 'ph.po_number')
                  ->whereRaw("grn.ln = ".DbExpression::lineNoInt('pl.line_no'));
            })
            ->select([
                DB::raw('ph.po_number as po_number'),
                DB::raw('MIN(ph.po_date) as first_order_date'),
                DB::raw('MAX(ph.po_date) as latest_order_date'),
                DB::raw('MIN(pl.eta_date) as first_deliv_date'),
                DB::raw('MAX(pl.eta_date) as latest_deliv_date'),
                DB::raw($hasVendorNumber ? DbExpression::stringAgg("NULLIF(ph.vendor_number,'')", ', ', true)." as vendor_number" : 'NULL as vendor_number'),
                DB::raw(DbExpression::stringAgg("ph.supplier", ', ', true)." as vendor_name"),
                DB::raw(DbExpression::stringAgg("NULLIF(pl.voyage_factory,'')", ', ', true)." as vendor_factories"),
                DB::raw('COUNT(DISTINCT ph.id) as header_count'),
                DB::raw('COUNT(pl.id) as total_lines'),
                DB::raw("$sumQtyOrderedExpr as total_qty_ordered"),
                DB::raw('COALESCE(SUM(grn.qty),0) as total_qty_received'),
                DB::raw("$sumOutstandingExpr as total_qty_outstanding"),
                DB::raw("$sumQtyToInvoiceExpr as total_qty_to_invoice"),
                DB::raw("$sumQtyToDeliverExpr as total_qty_to_deliver"),
                DB::raw("$storagesExpr as storage_locations"),
                DB::raw("$statusExpr as status_key"),
                // optional monetary and SAP status aggregates
                DB::raw($hasAmount ? 'SUM(COALESCE(pl.amount,0)) as total_amount' : 'NULL as total_amount'),
                DB::raw($hasSapStatus ? DbExpression::stringAgg("NULLIF(pl.sap_order_status,'')", ', ', true)." as sap_statuses" : "NULL as sap_statuses"),
            ])
            ->groupBy('ph.po_number');
    }
}
