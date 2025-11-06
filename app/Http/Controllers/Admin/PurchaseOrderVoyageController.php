<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PurchaseOrderVoyageController extends Controller
{
    public function index(Request $request, string $po): View
    {
        $poNumber = trim($po);

        $hasVoyage = [
            'bl' => Schema::hasColumn('po_lines', 'voyage_bl'),
            'etd' => Schema::hasColumn('po_lines', 'voyage_etd'),
            'eta' => Schema::hasColumn('po_lines', 'voyage_eta'),
            'factory' => Schema::hasColumn('po_lines', 'voyage_factory'),
            'status' => Schema::hasColumn('po_lines', 'voyage_status'),
            'issue' => Schema::hasColumn('po_lines', 'voyage_issue_date'),
            'expired' => Schema::hasColumn('po_lines', 'voyage_expired_date'),
            'remark' => Schema::hasColumn('po_lines', 'voyage_remark'),
        ];

        // PO summary (simple: vendor + period range)
        $headers = DB::table('po_headers')
            ->select(['id','po_number','po_date','supplier','vendor_number'])
            ->where('po_number', $poNumber)
            ->orderBy('po_date')
            ->get();

        abort_unless($headers->isNotEmpty(), 404);

        $summary = [
            'po_number' => $poNumber,
            'vendor_name' => $headers->pluck('supplier')->filter()->unique()->implode(', '),
            'vendor_number' => $headers->pluck('vendor_number')->filter()->unique()->implode(', '),
            'date_first' => optional($headers->first())->po_date,
            'date_last' => optional($headers->last())->po_date,
        ];

        // Filters
        $term = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $etaMonth = trim((string) $request->query('eta_month', ''));

        $q = DB::table('po_lines as pl')
            ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
            ->where('ph.po_number', $poNumber)
            ->select(array_filter([
                DB::raw('pl.id as id'),
                DB::raw("COALESCE(pl.line_no,'') as line_no"),
                DB::raw('pl.model_code as material'),
                DB::raw('pl.item_desc as item_desc'),
                DB::raw('pl.qty_ordered as qty_ordered'),
                DB::raw('pl.eta_date as delivery_date'),
                $hasVoyage['bl'] ? DB::raw('pl.voyage_bl as bl') : null,
                $hasVoyage['etd'] ? DB::raw('pl.voyage_etd as etd') : null,
                $hasVoyage['eta'] ? DB::raw('pl.voyage_eta as eta') : null,
                $hasVoyage['factory'] ? DB::raw('pl.voyage_factory as factory') : null,
                $hasVoyage['status'] ? DB::raw('pl.voyage_status as mstatus') : null,
                $hasVoyage['issue'] ? DB::raw('pl.voyage_issue_date as issue_date') : null,
                $hasVoyage['expired'] ? DB::raw('pl.voyage_expired_date as expired') : null,
                $hasVoyage['remark'] ? DB::raw('pl.voyage_remark as remark') : null,
            ]));

        if ($term !== '') {
            $like = '%'.$term.'%';
            $q->where(function ($w) use ($like) {
                $w->where('pl.model_code', 'like', $like)
                  ->orWhere('pl.item_desc', 'like', $like)
                  ->orWhere('pl.line_no', 'like', $like);
            });
        }
        if ($statusFilter !== '') {
            $q->where('pl.voyage_status', $statusFilter);
        }
        if ($etaMonth !== '') {
            // format YYYY-MM
            $q->whereRaw("to_char(pl.voyage_eta, 'YYYY-MM') = ?", [$etaMonth]);
        }

        $lines = $q->orderBy('pl.line_no')->paginate((int) min(max((int) $request->query('per_page', 25), 5), 100))
            ->appends($request->query());

        return view('admin.purchase_orders.voyage', compact('summary', 'lines', 'poNumber'));
    }

    public function bulkUpdate(Request $request, string $po): RedirectResponse
    {
        $payload = $request->validate([
            'rows' => ['required','array'],
            'rows.*.line_id' => ['required','integer','exists:po_lines,id'],
            'rows.*.bl' => ['nullable','string','max:100'],
            'rows.*.factory' => ['nullable','string','max:100'],
            'rows.*.status' => ['nullable','string','max:50'],
            'rows.*.issue_date' => ['nullable','date'],
            'rows.*.expired' => ['nullable','date'],
            'rows.*.etd' => ['nullable','date'],
            'rows.*.eta' => ['nullable','date'],
            'rows.*.remark' => ['nullable','string','max:500'],
        ]);

        $rows = $payload['rows'] ?? [];
        $saved = 0;
        DB::transaction(function () use ($rows, &$saved) {
            foreach ($rows as $row) {
                $id = (int) ($row['line_id'] ?? 0);
                if ($id <= 0) { continue; }
                $update = [];
                foreach ([
                    'voyage_bl' => 'bl',
                    'voyage_factory' => 'factory',
                    'voyage_status' => 'status',
                    'voyage_issue_date' => 'issue_date',
                    'voyage_expired_date' => 'expired',
                    'voyage_etd' => 'etd',
                    'voyage_eta' => 'eta',
                    'voyage_remark' => 'remark',
                ] as $col => $key) {
                    if (array_key_exists($key, $row)) { $update[$col] = $row[$key]; }
                }
                if (!empty($update)) {
                    DB::table('po_lines')->where('id', $id)->update(array_merge($update, ['updated_at' => now()]));
                    $saved++;
                }
            }
        });

        if ($request->wantsJson()) {
            return back()->with('status', "Saved: $saved rows");
        }
        return back()->with('status', "Saved: $saved rows");
    }
}

