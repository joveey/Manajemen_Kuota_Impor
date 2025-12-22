<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Quota;
use App\Models\PurchaseOrder;
use App\Models\PoLineVoyageSplit;
    use App\Models\QuotaHistory;
    use Illuminate\Support\Facades\Log;

    class PurchaseOrderVoyageController extends Controller
    {
        public function index(Request $request, string $po): View
        {
            $poNumber = trim($po);

            // Prefer purchase_orders as the primary source; fall back to po_headers/po_lines
            $purchaseOrdersTable = $this->resolveTableName('purchase_orders');
            $purchaseOrderColumns = $this->columnMap($purchaseOrdersTable);
            $poDocCol = $purchaseOrderColumns['po_doc'] ?? ($purchaseOrderColumns['po_number'] ?? null);

            $usingPurchaseOrders = false;
            $poRows = collect();
            if ($purchaseOrdersTable && $poDocCol) {
                try {
                    $poRows = DB::table($purchaseOrdersTable)
                        ->where($poDocCol, $poNumber)
                        ->get();
                    $usingPurchaseOrders = $poRows->isNotEmpty();
                } catch (\Throwable $e) {
                    $poRows = collect();
                    $usingPurchaseOrders = false;
                }
            }

            if ($usingPurchaseOrders) {
                $hasVoyage = [
                    'bl' => isset($purchaseOrderColumns['voyage_bl']),
                    'etd' => isset($purchaseOrderColumns['voyage_etd']),
                    'eta' => isset($purchaseOrderColumns['voyage_eta']),
                    'factory' => isset($purchaseOrderColumns['voyage_factory']),
                    'status' => isset($purchaseOrderColumns['voyage_status']),
                    'issue' => isset($purchaseOrderColumns['voyage_issue_date']),
                    'expired' => isset($purchaseOrderColumns['voyage_expired_date']),
                    'remark' => isset($purchaseOrderColumns['voyage_remark']),
                ];

                $poDateCol = $purchaseOrderColumns['created_date']
                    ?? ($purchaseOrderColumns['order_date']
                    ?? ($purchaseOrderColumns['po_date']
                    ?? ($purchaseOrderColumns['created_at'] ?? null)));
                $vendorNameCol = $purchaseOrderColumns['vendor_name'] ?? null;
                $vendorNumberCol = $purchaseOrderColumns['vendor_no'] ?? ($purchaseOrderColumns['vendor_number'] ?? null);

                $summary = [
                    'po_number' => $poNumber,
                    'vendor_name' => $vendorNameCol ? $poRows->pluck($vendorNameCol)->filter()->unique()->implode(', ') : '',
                    'vendor_number' => $vendorNumberCol ? $poRows->pluck($vendorNumberCol)->filter()->unique()->implode(', ') : '',
                    'date_first' => $poDateCol ? $poRows->min($poDateCol) : null,
                    'date_last' => $poDateCol ? $poRows->max($poDateCol) : null,
                ];

                // Filters
                $term = trim((string) $request->query('q', ''));
                $statusFilter = trim((string) $request->query('status', ''));
                $etaMonth = trim((string) $request->query('eta_month', ''));

                $lineNoCol = $purchaseOrderColumns['line_no'] ?? ($purchaseOrderColumns['line_number'] ?? null);
                $materialCol = $purchaseOrderColumns['item_code'] ?? ($purchaseOrderColumns['model_code'] ?? null);
                $itemDescCol = $purchaseOrderColumns['item_desc'] ?? ($purchaseOrderColumns['item_description'] ?? null);
                $qtyCol = $purchaseOrderColumns['qty'] ?? ($purchaseOrderColumns['quantity'] ?? ($purchaseOrderColumns['qty_ordered'] ?? null));
                $deliveryCol = $purchaseOrderColumns['eta_date'] ?? ($purchaseOrderColumns['delivery_date'] ?? ($purchaseOrderColumns['order_date'] ?? null));
                $idExpr = isset($purchaseOrderColumns['id'])
                    ? DB::raw($purchaseOrderColumns['id'].' as id')
                    : DB::raw('ROW_NUMBER() OVER (ORDER BY '.($lineNoCol ?? $poDocCol ?? '1').') as id');

                $q = DB::table($purchaseOrdersTable)
                    ->where($poDocCol, $poNumber)
                    ->select(array_filter([
                        $idExpr,
                        $lineNoCol ? DB::raw("COALESCE($lineNoCol,'') as line_no") : null,
                        $materialCol ? DB::raw("$materialCol as material") : null,
                        $itemDescCol ? DB::raw("$itemDescCol as item_desc") : null,
                        $qtyCol ? DB::raw("$qtyCol as qty_ordered") : DB::raw('0 as qty_ordered'),
                        $deliveryCol ? DB::raw("$deliveryCol as delivery_date") : DB::raw('NULL as delivery_date'),
                        $hasVoyage['bl'] ? DB::raw($purchaseOrderColumns['voyage_bl'].' as bl') : DB::raw('NULL as bl'),
                        $hasVoyage['etd'] ? DB::raw($purchaseOrderColumns['voyage_etd'].' as etd') : DB::raw('NULL as etd'),
                        $hasVoyage['eta'] ? DB::raw($purchaseOrderColumns['voyage_eta'].' as eta') : DB::raw('NULL as eta'),
                        $hasVoyage['factory'] ? DB::raw($purchaseOrderColumns['voyage_factory'].' as factory') : DB::raw('NULL as factory'),
                        $hasVoyage['status'] ? DB::raw($purchaseOrderColumns['voyage_status'].' as mstatus') : DB::raw('NULL as mstatus'),
                        $hasVoyage['remark'] ? DB::raw($purchaseOrderColumns['voyage_remark'].' as remark') : DB::raw('NULL as remark'),
                    ]));

                if ($term !== '') {
                    $like = '%'.$term.'%';
                    $q->where(function ($w) use ($like, $materialCol, $itemDescCol, $lineNoCol) {
                        if ($materialCol) { $w->orWhere($materialCol, 'like', $like); }
                        if ($itemDescCol) { $w->orWhere($itemDescCol, 'like', $like); }
                        if ($lineNoCol) { $w->orWhere($lineNoCol, 'like', $like); }
                    });
                }
                if ($statusFilter !== '' && $hasVoyage['status']) {
                    $q->where($purchaseOrderColumns['voyage_status'], $statusFilter);
                }
                if ($etaMonth !== '' && $hasVoyage['eta']) {
                    $q->whereRaw("to_char(".$purchaseOrderColumns['voyage_eta'].", 'YYYY-MM') = ?", [$etaMonth]);
                }

                $lines = $q->orderBy($lineNoCol ?? 'id')->paginate((int) min(max((int) $request->query('per_page', 25), 5), 100))
                    ->appends($request->query());

                $splitsByLine = [];
                $sumByLine = [];

                $lines->setCollection(
                    $lines->getCollection()->map(function ($ln) use ($sumByLine) {
                        $ordered = (float) ($ln->qty_ordered ?? 0);
                        $used = (float) ($sumByLine[$ln->id] ?? 0);
                        $ln->qty_remaining = max($ordered - $used, 0);
                        return $ln;
                    })
                );

                $allQuotas = \App\Models\Quota::query()
                    ->orderByDesc('period_start')
                    ->get(['id','quota_number','government_category','period_start','period_end','total_allocation','forecast_remaining','min_pk','max_pk','is_min_inclusive','is_max_inclusive','status','is_active']);

                $quotaOptionsByLine = [];
                $lines->getCollection()->each(function ($ln) use (&$quotaOptionsByLine, $allQuotas) {
                    $p = new \App\Models\Product();
                    $p->hs_code = $ln->hs_code ?? null;
                    $p->pk_capacity = $ln->pk_capacity ?? null;
                    $opts = [];
                    foreach ($allQuotas as $q) {
                        if ($q->matchesProduct($p)) {
                            $opts[] = [
                                'id' => (int) $q->id,
                                'quota_number' => (string) $q->quota_number,
                                'desc' => (string) ($q->government_category ?? ''),
                                'start' => $q->period_start ? $q->period_start->format('Y-m-d') : '-',
                                'end' => $q->period_end ? $q->period_end->format('Y-m-d') : '-',
                                'rem' => (int) ($q->forecast_remaining ?? 0),
                            ];
                        }
                    }
                    $quotaOptionsByLine[$ln->id] = $opts;
                });

                // Detect source quota per line using current PO -> purchase_order_quota pivot, filtered by HS/PK
                $sourceQuotaByLine = [];
                $poRecord = $poRows->first();
                if ($poRecord && isset($poRecord->id)) {
                    $pivots = DB::table('purchase_order_quota as pq')
                        ->join('quotas as q','pq.quota_id','=','q.id')
                        ->select('pq.purchase_order_id','pq.quota_id','pq.allocated_qty','q.quota_number','q.period_start','q.period_end')
                        ->where('pq.purchase_order_id', $poRecord->id)
                        ->get();
                    $pivotQuotaIds = $pivots->pluck('quota_id')->unique()->all();
                    $pivotQuotas = \App\Models\Quota::whereIn('id', $pivotQuotaIds)->get()->keyBy('id');
                    $lines->getCollection()->each(function ($ln) use ($pivots, $pivotQuotas, &$sourceQuotaByLine) {
                        $p = new \App\Models\Product();
                        $p->hs_code = $ln->hs_code ?? null;
                        $p->pk_capacity = $ln->pk_capacity ?? null;
                        $candidates = [];
                        foreach ($pivots as $pv) {
                            $q = $pivotQuotas->get($pv->quota_id);
                            if ($q && $q->matchesProduct($p)) {
                                $candidates[] = $pv;
                            }
                        }
                        if (!empty($candidates)) {
                            usort($candidates, fn($a,$b)=>((int)$b->allocated_qty <=> (int)$a->allocated_qty));
                            $top = $candidates[0];
                            $label = $top->quota_number.' ('.($top->period_start ? \Illuminate\Support\Carbon::parse($top->period_start)->format('Y-m-d') : '-')
                                .'..'.($top->period_end ? \Illuminate\Support\Carbon::parse($top->period_end)->format('Y-m-d') : '-').')';
                            $sourceQuotaByLine[$ln->id] = ['id'=>(int)$top->quota_id,'label'=>$label];
                        }
                    });
                }

                return view('admin.purchase_orders.voyage', compact('summary', 'lines', 'poNumber', 'splitsByLine','quotaOptionsByLine','sourceQuotaByLine'))
                    ->with('usingPurchaseOrders', true);
            }

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

        // Compose vendor label and append distinct factories if available
        $vendorName = $headers->pluck('supplier')->filter()->unique()->implode(', ');
        $factories = DB::table('po_lines as pl')
            ->join('po_headers as ph','pl.po_header_id','=','ph.id')
            ->where('ph.po_number', $poNumber)
            ->whereNotNull('pl.voyage_factory')
            ->whereRaw("NULLIF(pl.voyage_factory,'') <> ''")
            ->pluck('pl.voyage_factory')
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
        if ($factories) { $vendorName = trim($vendorName.' - '.$factories); }

        $summary = [
            'po_number' => $poNumber,
            'vendor_name' => $vendorName,
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
            ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
            ->where('ph.po_number', $poNumber)
            ->select(array_filter([
                DB::raw('pl.id as id'),
                DB::raw("COALESCE(pl.line_no,'') as line_no"),
                DB::raw('pl.model_code as material'),
                DB::raw('pl.item_desc as item_desc'),
                DB::raw('pl.qty_ordered as qty_ordered'),
                DB::raw('pl.eta_date as delivery_date'),
                DB::raw('hs.hs_code as hs_code'),
                DB::raw('hs.pk_capacity as pk_capacity'),
                $hasVoyage['bl'] ? DB::raw('pl.voyage_bl as bl') : null,
                $hasVoyage['etd'] ? DB::raw('pl.voyage_etd as etd') : null,
                $hasVoyage['eta'] ? DB::raw('pl.voyage_eta as eta') : null,
                $hasVoyage['factory'] ? DB::raw('pl.voyage_factory as factory') : null,
                $hasVoyage['status'] ? DB::raw('pl.voyage_status as mstatus') : null,
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

        // Fetch existing split voyages per line
        $lineIds = collect($lines->items())->pluck('id')->all();
        $splitsByLine = [];
        $sumByLine = [];
        if (!empty($lineIds) && \Illuminate\Support\Facades\Schema::hasTable('po_line_voyage_splits')) {
            $rows = DB::table('po_line_voyage_splits')
                ->whereIn('po_line_id', $lineIds)
                ->orderBy('po_line_id')->orderBy('seq_no')->orderBy('id')
                ->get();
            foreach ($rows as $r) {
                $splitsByLine[$r->po_line_id][] = $r;
                $sumByLine[$r->po_line_id] = ($sumByLine[$r->po_line_id] ?? 0) + (float) ($r->qty ?? 0);
            }
        }

        // Derive remaining qty (ordered - sum of split qty) for display
        $lines->setCollection(
            $lines->getCollection()->map(function ($ln) use ($sumByLine) {
                $ordered = (float) ($ln->qty_ordered ?? 0);
                $used = (float) ($sumByLine[$ln->id] ?? 0);
                $ln->qty_remaining = max($ordered - $used, 0);
                return $ln;
            })
        );

        // Provide per-line quota options filtered by HS/PK
        $allQuotas = \App\Models\Quota::query()
            ->orderByDesc('period_start')
            ->get(['id','quota_number','government_category','period_start','period_end','total_allocation','forecast_remaining','min_pk','max_pk','is_min_inclusive','is_max_inclusive','status','is_active']);

        $quotaOptionsByLine = [];
        $lines->getCollection()->each(function ($ln) use (&$quotaOptionsByLine, $allQuotas) {
            $p = new \App\Models\Product();
            $p->hs_code = $ln->hs_code ?? null;
            $p->pk_capacity = $ln->pk_capacity ?? null;
            $opts = [];
            foreach ($allQuotas as $q) {
                if ($q->matchesProduct($p)) {
                    $opts[] = [
                        'id' => (int) $q->id,
                        'quota_number' => (string) $q->quota_number,
                        'desc' => (string) ($q->government_category ?? ''),
                        'start' => $q->period_start ? $q->period_start->format('Y-m-d') : '-',
                        'end' => $q->period_end ? $q->period_end->format('Y-m-d') : '-',
                        'rem' => (int) ($q->forecast_remaining ?? 0),
                    ];
                }
            }
            $quotaOptionsByLine[$ln->id] = $opts;
        });

        // Detect source quota per line using current PO -> purchase_order_quota pivot, filtered by HS/PK
        $sourceQuotaByLine = [];
        $poRecord = null;
        if ($purchaseOrdersTable && $poDocCol) {
            try {
                $poRecord = DB::table($purchaseOrdersTable)->where($poDocCol, $poNumber)->first();
            } catch (\Throwable $e) {
                $poRecord = null;
            }
        }
        if ($poRecord) {
            $pivots = DB::table('purchase_order_quota as pq')
                ->join('quotas as q','pq.quota_id','=','q.id')
                ->select('pq.purchase_order_id','pq.quota_id','pq.allocated_qty','q.quota_number','q.period_start','q.period_end')
                ->where('pq.purchase_order_id', $poRecord->id)
                ->get();
            $pivotQuotaIds = $pivots->pluck('quota_id')->unique()->all();
            $pivotQuotas = \App\Models\Quota::whereIn('id', $pivotQuotaIds)->get()->keyBy('id');
            $lines->getCollection()->each(function ($ln) use ($pivots, $pivotQuotas, &$sourceQuotaByLine) {
                $p = new \App\Models\Product();
                $p->hs_code = $ln->hs_code ?? null;
                $p->pk_capacity = $ln->pk_capacity ?? null;
                $candidates = [];
                foreach ($pivots as $pv) {
                    $q = $pivotQuotas->get($pv->quota_id);
                    if ($q && $q->matchesProduct($p)) {
                        $candidates[] = $pv;
                    }
                }
                if (!empty($candidates)) {
                    usort($candidates, fn($a,$b)=>((int)$b->allocated_qty <=> (int)$a->allocated_qty));
                    $top = $candidates[0];
                    $label = $top->quota_number.' ('.($top->period_start ? \Illuminate\Support\Carbon::parse($top->period_start)->format('Y-m-d') : '-')
                        .'..'.($top->period_end ? \Illuminate\Support\Carbon::parse($top->period_end)->format('Y-m-d') : '-').')';
                    $sourceQuotaByLine[$ln->id] = ['id'=>(int)$top->quota_id,'label'=>$label];
                }
            });
        }

        return view('admin.purchase_orders.voyage', compact('summary', 'lines', 'poNumber', 'splitsByLine','quotaOptionsByLine','sourceQuotaByLine'))
            ->with('usingPurchaseOrders', false);
    }

    public function bulkUpdate(Request $request, string $po): RedirectResponse
    {
        $poNumber = trim($po);
        $purchaseOrdersTable = $this->resolveTableName('purchase_orders');
        $purchaseOrderColumns = $this->columnMap($purchaseOrdersTable);
        $poDocCol = $purchaseOrderColumns['po_doc'] ?? ($purchaseOrderColumns['po_number'] ?? null);
        $usingPurchaseOrders = false;
        if ($purchaseOrdersTable && $poDocCol) {
            try {
                $usingPurchaseOrders = DB::table($purchaseOrdersTable)
                    ->where($poDocCol, $poNumber)
                    ->exists();
            } catch (\Throwable $e) {
                $usingPurchaseOrders = false;
            }
        }

        $lineExistsRule = $usingPurchaseOrders
            ? ['required','integer','exists:'.$purchaseOrdersTable.',id']
            : ['required','integer','exists:po_lines,id'];

        $splitLineRule = $usingPurchaseOrders
            ? ['nullable','integer']
            : ['required_without:splits.*.id','integer','exists:po_lines,id'];

        $payload = $request->validate([
            'rows' => ['sometimes','array'],
            'rows.*.line_id' => $lineExistsRule,
            'rows.*.bl' => ['nullable','string','max:100'],
            'rows.*.factory' => ['nullable','string','max:100'],
            'rows.*.status' => ['nullable','string','max:50'],
            'rows.*.etd' => ['nullable','date'],
            'rows.*.eta' => ['nullable','date'],
            'rows.*.remark' => ['nullable','string','max:500'],
            // optional split rows (insert/update/delete) can be passed as JSON via splits_json
            'splits' => ['sometimes','array'],
            'splits.*.id' => ['nullable','integer'],
            'splits.*.line_id' => $splitLineRule,
            'splits.*.qty' => ['nullable','numeric'],
            'splits.*.seq_no' => ['nullable','integer','min:1'],
            'splits.*.bl' => ['nullable','string','max:100'],
            'splits.*.factory' => ['nullable','string','max:100'],
            'splits.*.status' => ['nullable','string','max:50'],
            'splits.*.etd' => ['nullable','date'],
            'splits.*.eta' => ['nullable','date'],
            'splits.*.remark' => ['nullable','string','max:500'],
            'splits.*.delete' => ['nullable','boolean'],
            'splits_json' => ['sometimes','string'],
            'rows_json' => ['sometimes','string'],
        ]);

        // rows may come as array or JSON (rows_json)
        $rows = $payload['rows'] ?? [];
        if (empty($rows) && $request->filled('rows_json')) {
            try { $rows = json_decode((string) $request->input('rows_json'), true) ?: []; } catch (\Throwable $e) { $rows = []; }
        }
        $saved = 0;
        // Parse splits JSON if provided
        $splits = $payload['splits'] ?? [];
        if (empty($splits) && !empty($payload['splits_json'] ?? '')) {
            try { $splits = json_decode((string) $payload['splits_json'], true) ?: []; } catch (\Throwable $e) { $splits = []; }
        }

        DB::transaction(function () use ($rows, $splits, &$saved, $poNumber, $usingPurchaseOrders, $purchaseOrdersTable, $poDocCol, $purchaseOrderColumns) {
            if ($usingPurchaseOrders) {
                $dateCols = array_filter([
                    $purchaseOrderColumns['voyage_etd'] ?? null,
                    $purchaseOrderColumns['voyage_eta'] ?? null,
                ]);
                foreach ($rows as $row) {
                    $id = (int) ($row['line_id'] ?? 0);
                    if ($id <= 0) { continue; }
                    $update = [];
                    foreach ([
                        'voyage_bl' => 'bl',
                        'voyage_factory' => 'factory',
                        'voyage_status' => 'status',
                        'voyage_etd' => 'etd',
                        'voyage_eta' => 'eta',
                        'voyage_remark' => 'remark',
                    ] as $col => $key) {
                        if (!isset($purchaseOrderColumns[$col])) { continue; }
                        if (array_key_exists($key, $row)) {
                            $update[$purchaseOrderColumns[$col]] = $row[$key];
                        }
                    }
                    if (!empty($update)) {
                        foreach ($update as $k => $v) {
                            if (is_string($v)) { $v = trim($v); }
                            if ($v === '') { $v = null; }
                            if (in_array($k, $dateCols, true)) {
                                if ($v !== null) {
                                    try {
                                        if (is_string($v) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $v)) {
                                            [$d,$m,$y] = explode('-', $v);
                                            $v = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                                        } else {
                                            $v = \Illuminate\Support\Carbon::parse((string)$v)->toDateString();
                                        }
                                    } catch (\Throwable $e) { $v = null; }
                                }
                            }
                            $update[$k] = $v;
                        }
                        $query = DB::table($purchaseOrdersTable)->where('id', $id);
                        if ($poDocCol) {
                            $query->where($poDocCol, $poNumber);
                        }
                        $query->update(array_merge($update, Schema::hasColumn($purchaseOrdersTable, 'updated_at') ? ['updated_at' => now()] : []));
                        $saved++;
                    }
                }
                return;
            }

            foreach ($rows as $row) {
                $id = (int) ($row['line_id'] ?? 0);
                if ($id <= 0) { continue; }
                $update = [];
                foreach ([
                    'voyage_bl' => 'bl',
                    'voyage_factory' => 'factory',
                    'voyage_status' => 'status',
                    'voyage_etd' => 'etd',
                    'voyage_eta' => 'eta',
                    'voyage_remark' => 'remark',
                ] as $col => $key) {
                    if (array_key_exists($key, $row)) { $update[$col] = $row[$key]; }
                }
                // sanitize: empty string -> null; parse dd-mm-yyyy to Y-m-d for date fields
                if (!empty($update)) {
                    foreach ($update as $k => $v) {
                        if (is_string($v)) { $v = trim($v); }
                        if ($v === '') { $v = null; }
                        // normalize dates
                        if (in_array($k, ['voyage_etd','voyage_eta'], true)) {
                            if ($v !== null) {
                                try {
                                    if (is_string($v) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $v)) {
                                        [$d,$m,$y] = explode('-', $v);
                                        $v = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                                    } else {
                                        $v = \Illuminate\Support\Carbon::parse((string)$v)->toDateString();
                                    }
                                } catch (\Throwable $e) { $v = null; }
                            }
                        }
                        $update[$k] = $v;
                    }
                    DB::table('po_lines')->where('id', $id)->update(array_merge($update, ['updated_at' => now()]));
                    $saved++;
                }
            }

            // Upsert/delete splits
            if (!empty($splits) && \Illuminate\Support\Facades\Schema::hasTable('po_line_voyage_splits')) {
                foreach ($splits as $sp) {
                    $sid = (int) ($sp['id'] ?? 0);
                    $delete = (bool) ($sp['delete'] ?? false);

                    $parentLineId = (int) ($sp['line_id'] ?? 0);
                    if ($parentLineId <= 0) { continue; }

                    // Lock parent line to compute current remaining and enforce invariants (never update parent qty)
                    $parentLine = DB::table('po_lines')->where('id', $parentLineId)->lockForUpdate()->first();
                    if (!$parentLine) { continue; }
                    $existingSum = (float) DB::table('po_line_voyage_splits')
                        ->where('po_line_id', $parentLineId)
                        ->sum('qty');
                    $orderedQty = (float) ($parentLine->qty_ordered ?? 0);

                    // Build base data (always use parent po_line_id)
                    $data = [
                        'po_line_id' => $parentLineId,
                        'voyage_bl' => ($sp['bl'] ?? '') !== '' ? trim((string)$sp['bl']) : null,
                        'voyage_etd' => null,
                        'voyage_eta' => null,
                        'voyage_factory' => ($sp['factory'] ?? '') !== '' ? trim((string)$sp['factory']) : null,
                        'voyage_status' => ($sp['status'] ?? '') !== '' ? trim((string)$sp['status']) : null,
                        'voyage_remark' => ($sp['remark'] ?? '') !== '' ? trim((string)$sp['remark']) : null,
                        'updated_at' => now(),
                    ];
                    if (array_key_exists('seq_no', $sp)) {
                        $data['seq_no'] = (int) max((int) $sp['seq_no'], 1);
                    }
                    if (array_key_exists('qty', $sp)) {
                        $q = (float) $sp['qty'];
                        if ($sid > 0) {
                            if ($q > 0) { $data['qty'] = $q; }
                        } else {
                            $data['qty'] = max($q, 0);
                        }
                    }
                    foreach (['etd' => 'voyage_etd', 'eta' => 'voyage_eta'] as $src => $dst) {
                        $val = $sp[$src] ?? null;
                        if (is_string($val)) { $val = trim($val); }
                        if ($val === '' || $val === null) { $data[$dst] = null; }
                        else {
                            try {
                                if (preg_match('/^\d{2}-\d{2}-\d{4}$/', (string)$val)) {
                                    [$d,$m,$y] = explode('-', (string)$val);
                                    $data[$dst] = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                                } else {
                                    $data[$dst] = \Illuminate\Support\Carbon::parse((string)$val)->toDateString();
                                }
                            } catch (\Throwable $e) { $data[$dst] = null; }
                        }
                    }

                    if ($sid > 0 && $delete) {
                        // Delete split and roll back any net forecast/pivot effects derived from DB history.
                        $splitRow = DB::table('po_line_voyage_splits')
                            ->where('id', $sid)
                            ->where('po_line_id', $parentLineId)
                            ->lockForUpdate()
                            ->first();
                        if ($splitRow) {
                            $poModel = PurchaseOrder::where('po_doc', $poNumber)->first();

                            if ($poModel) {
                                $splitModel = PoLineVoyageSplit::find($splitRow->id);
                                if ($splitModel) {
                                    // Aggregate net forecast change per quota for this split
                                    $hist = DB::table('quota_histories')
                                        ->select('quota_id', DB::raw('SUM(quantity_change) as net_qty'))
                                        ->whereIn('change_type', [QuotaHistory::TYPE_FORECAST_DECREASE, QuotaHistory::TYPE_FORECAST_INCREASE])
                                        ->where('reference_type', PoLineVoyageSplit::class)
                                        ->where('reference_id', $splitModel->id)
                                        ->groupBy('quota_id')
                                        ->get();

                                    if ($hist->isNotEmpty()) {
                                        $poDate = DB::table('po_headers')->where('po_number', $poNumber)->value('po_date');
                                        $occ = $poDate ? new \DateTimeImmutable((string)$poDate) : null;
                                        $userId = Auth::id();

                                        Log::info('voyage_delete_split', [
                                            'po_number' => $poNumber,
                                            'split_id' => $splitModel->id,
                                            'net_hist' => $hist->map(fn($r) => [
                                                'quota_id' => $r->quota_id,
                                                'net_qty' => (int) $r->net_qty,
                                            ])->all(),
                                        ]);

                                        foreach ($hist as $row) {
                                            $quotaId = (int) $row->quota_id;
                                            $net = (int) $row->net_qty;
                                            if ($net === 0) {
                                                continue;
                                            }
                                            $quota = Quota::lockForUpdate()->find($quotaId);
                                            if (!$quota) {
                                                continue;
                                            }

                                            if ($net > 0) {
                                                $quota->decrementForecast($net, 'Voyage split delete (rollback)', $splitModel, $occ, $userId);
                                            } else {
                                                $quota->incrementForecast(-$net, 'Voyage split delete (rollback)', $splitModel, $occ, $userId);
                                            }

                                            // Restore pivot allocation for this PO/quota pair by adding the net.
                                            $pivotRow = DB::table('purchase_order_quota')
                                                ->where('purchase_order_id', $poModel->id)
                                                ->where('quota_id', $quotaId)
                                                ->lockForUpdate()
                                                ->first();
                                            $newAlloc = ($pivotRow ? (int) $pivotRow->allocated_qty : 0) + $net;
                                            if ($newAlloc > 0) {
                                                if ($pivotRow) {
                                                    DB::table('purchase_order_quota')->where('id', $pivotRow->id)->update([
                                                        'allocated_qty' => $newAlloc,
                                                        'updated_at' => now(),
                                                    ]);
                                                } else {
                                                    DB::table('purchase_order_quota')->insert([
                                                        'purchase_order_id' => $poModel->id,
                                                        'quota_id' => $quotaId,
                                                        'allocated_qty' => $newAlloc,
                                                        'created_at' => now(),
                                                        'updated_at' => now(),
                                                    ]);
                                                }
                                            } elseif ($pivotRow) {
                                                DB::table('purchase_order_quota')->where('id', $pivotRow->id)->delete();
                                            }
                                        }
                                    }
                                }
                            }

                            // Finally remove the split row. If the split never left its
                            // original quota (no history), quotas/pivots remain unchanged.
                            DB::table('po_line_voyage_splits')->where('id', $sid)->delete();
                        }
                        continue;
                    }
                    if ($sid > 0) {
                        // Update split quantity with validation against remaining; do not touch parent qty
                        $prev = DB::table('po_line_voyage_splits')
                            ->where('id', $sid)
                            ->where('po_line_id', $parentLineId)
                            ->lockForUpdate()
                            ->first();
                        if (!$prev) { continue; }
                        $prevQty = (float) ($prev->qty ?? 0);
                        if (array_key_exists('qty', $sp)) {
                            $raw = $sp['qty'];
                            // If user didn't touch qty (empty/null), skip qty update
                            if (!($raw === '' || $raw === null)) {
                                $newQty = (float) $raw;
                                if ($newQty > 0) {
                                    $sumOther = max(0, $existingSum - $prevQty); // other splits on this line
                                    $cap = max(0, $orderedQty - $sumOther);
                                    if ($newQty > $cap) { $newQty = $cap; }
                                    $data['qty'] = $newQty;
                                }
                                // If <= 0 and not explicitly deleting, keep previous qty (no-op)
                            }
                        }
                        DB::table('po_line_voyage_splits')->where('id', $sid)->update($data);
                    } else {
                        // Insert new split against remaining parent
                        if (!array_key_exists('qty', $sp) || (float) $sp['qty'] <= 0) { continue; }
                        $splitQty = max(0, (float) $sp['qty']);
                        $remainingBefore = max(0, $orderedQty - $existingSum);
                        if ($splitQty > $remainingBefore) { $splitQty = $remainingBefore; }
                        if ($splitQty <= 0) { continue; }
                        $data['qty'] = $splitQty;
                        if (!array_key_exists('seq_no', $sp)) { $data['seq_no'] = 1; }
                        $data['created_at'] = now();
                        $data['created_by'] = Auth::id();
                        DB::table('po_line_voyage_splits')->insert($data);
                    }
                }
            }
        });

        // Enrich audit log with concise counters so labels are clearer
        try {
            $request->attributes->set('audit_extra', [
                'saved_rows' => (int) $saved,
                'rows_count' => is_array($rows) ? count($rows) : 0,
                'splits' => is_array($splits) ? array_values($splits) : [],
            ]);
        } catch (\Throwable $e) { /* ignore */ }

        if ($request->wantsJson()) {
            return back()->with('status', "Saved: $saved rows");
        }
        return back()->with('status', "Saved: $saved rows");
    }
public function moveSplitQuota(Request $request, string $po): RedirectResponse
    {
        $data = $request->validate([
            'line_id' => ['required','integer','exists:po_lines,id'],
            'split_id' => ['required','integer','exists:po_line_voyage_splits,id'],
            // source_quota_id is accepted for basic sanity/UX but the actual
            // CURRENT quota for the split is derived from persisted DB state
            // (quota_histories + purchase_order_quota), not from this field.
            'source_quota_id' => ['required','integer','exists:quotas,id'],
            // target_quota_id must exist; no-op check is done manually against the
            // split's CURRENT quota derived from DB, so moving back to the original
            // quota (A->B->A) is allowed.
            'target_quota_id' => ['required','integer','exists:quotas,id'],
            'move_qty' => ['nullable','numeric','min:1'],
            'eta_date' => ['nullable','date'],
        ]);

        // Verify line belongs to PO number in route
        $poHeader = DB::table('po_headers')->where('po_number', $po)->first();
        abort_unless($poHeader, 404);
        $line = DB::table('po_lines')->where('id', $data['line_id'])->where('po_header_id', $poHeader->id)->first();
        abort_unless($line, 404);
        $splitRow = DB::table('po_line_voyage_splits')->where('id', $data['split_id'])->where('po_line_id', $line->id)->first();
        abort_unless($splitRow, 404);

        $splitModel = PoLineVoyageSplit::findOrFail((int) $splitRow->id);

        $poModel = PurchaseOrder::where('po_doc', $po)->first();
        if (!$poModel) {
            // Create a minimal PO record to satisfy references if needed
            $prod = \App\Models\Product::query()->firstOrCreate(['code' => (string)($line->model_code ?? 'UNKNOWN')], [
                'name' => (string)($line->model_code ?? 'UNKNOWN'),
                'sap_model' => (string)($line->model_code ?? 'UNKNOWN'),
                'is_active' => true,
            ]);
            $poModel = PurchaseOrder::create([
                'po_number' => (string) $po,
                'product_id' => $prod->id,
                'quantity' => (int) max((int)($line->qty_ordered ?? 0), 0),
                'order_date' => $poHeader->po_date ?? now()->toDateString(),
                'vendor_name' => (string) ($poHeader->supplier ?? ''),
                'status' => PurchaseOrder::STATUS_ORDERED,
                'plant_name' => 'Voyage',
                'plant_detail' => 'Voyage Move Quota',
            ]);
        }
        $targetQuotaId = (int) $data['target_quota_id'];

        $eta = $data['eta_date'] ?? ($splitRow->voyage_eta ?? null);
        $occurredOn = $eta ? new \DateTimeImmutable((string)$eta) : null;
        $userId = Auth::id();

        // Qty defaults to split qty when not provided
        $qty = (float) ($data['move_qty'] ?? $splitRow->qty ?? 0);
        $qty = max(0, (float)$qty);
        if ($qty <= 0) { return back()->withErrors(['move_qty' => 'Quantity to move must be > 0']); }

        // Resolve CURRENT quota purely from persisted history:
        // - Sum quantity_change per quota for forecast inc/dec rows of this split
        // - If any net is negative, pick the most negative as CURRENT
        // - If all nets are zero, fall back to source_quota_id only when that
        //   quota exists in purchase_order_quota for this purchase order
        $hist = DB::table('quota_histories')
            ->select('quota_id', DB::raw('SUM(quantity_change) as net_qty'))
            ->whereIn('change_type', [QuotaHistory::TYPE_FORECAST_DECREASE, QuotaHistory::TYPE_FORECAST_INCREASE])
            ->where('reference_type', PoLineVoyageSplit::class)
            ->where('reference_id', $splitModel->id)
            ->groupBy('quota_id')
            ->get();

        $currentQuotaId = null;
        if ($hist->isNotEmpty()) {
            $neg = $hist->filter(fn($row) => (float) $row->net_qty < 0);
            if ($neg->isNotEmpty()) {
                $row = $neg->sortBy('net_qty')->first(); // most negative net qty
                $currentQuotaId = (int) $row->quota_id;
            }
        }
        if ($currentQuotaId === null) {
            $candidate = (int) $data['source_quota_id'];
            // Fallback to the original quota (typically the line's PO quota) when net history is zeroed out (A->B->A or never moved)
            if ($poModel) {
                $inPivot = DB::table('purchase_order_quota')
                    ->where('purchase_order_id', $poModel->id)
                    ->where('quota_id', $candidate)
                    ->exists();
                if ($inPivot) {
                    $currentQuotaId = $candidate;
                }
            }
            // Even if pivot row was removed after an A->B->A sequence, allow using the provided source quota as the current reference
            if ($currentQuotaId === null) {
                $currentQuotaId = $candidate;
            }
        }

        if ($currentQuotaId === null) {
            return back()->withErrors([
                'target_quota_id' => 'Unable to resolve current quota for this split. Please reload the page and try again.',
            ]);
        }

        // Block only true no-op: when target equals CURRENT quota. Moving back to
        // the original quota (A->B->A) is allowed because histories will sum to zero.
        if ($targetQuotaId === $currentQuotaId) {
            return back()->withErrors([
                'target_quota_id' => 'Target quota must be different from the current quota.',
            ]);
        }

        $sourceQuota = null;
        $targetQuota = null;

        DB::transaction(function () use ($poModel, $currentQuotaId, $targetQuotaId, $qty, $occurredOn, $userId, $splitModel, &$sourceQuota, &$targetQuota, $po) {
            // Lock quotas in deterministic order to reduce deadlock risk
            $ids = [$currentQuotaId, $targetQuotaId];
            sort($ids);
            $locked = Quota::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $sourceQuota = $locked->get($currentQuotaId);
            $targetQuota = $locked->get($targetQuotaId);

            if (!$sourceQuota || !$targetQuota) {
                throw new \RuntimeException('Unable to lock quotas for split move.');
            }

            $sourceQuota->incrementForecast((int)$qty, 'Voyage split reallocation (refund)', $splitModel, $occurredOn, $userId);
            $targetQuota->decrementForecast((int)$qty, 'Voyage split reallocation (reserve)', $splitModel, $occurredOn, $userId);

            // Pivot update scoped to this purchase order
            $pivotSrc = DB::table('purchase_order_quota')
                ->where('purchase_order_id', $poModel->id)
                ->where('quota_id', $sourceQuota->id)
                ->first();
            if ($pivotSrc) {
                $newAlloc = max(0, (int)$pivotSrc->allocated_qty - (int)$qty);
                if ($newAlloc > 0) {
                    DB::table('purchase_order_quota')->where('id', $pivotSrc->id)->update([
                        'allocated_qty' => $newAlloc,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('purchase_order_quota')->where('id', $pivotSrc->id)->delete();
                }
            }

            $pivotTgt = DB::table('purchase_order_quota')
                ->where('purchase_order_id', $poModel->id)
                ->where('quota_id', $targetQuota->id)
                ->first();
            if ($pivotTgt) {
                DB::table('purchase_order_quota')->where('id', $pivotTgt->id)->update([
                    'allocated_qty' => (int)$pivotTgt->allocated_qty + (int)$qty,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('purchase_order_quota')->insert([
                    'purchase_order_id' => $poModel->id,
                    'quota_id' => $targetQuota->id,
                    'allocated_qty' => (int) $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('voyage_move_split', [
                'po_number' => $po,
                'split_id' => $splitModel->id,
                'qty' => (int) $qty,
                'source_quota_id' => $sourceQuota->id,
                'target_quota_id' => $targetQuota->id,
            ]);
        });

        return back()->with('status', sprintf('Moved %s units (forecast) from quota %s to %s for split #%d.', number_format($qty), $sourceQuota->quota_number, $targetQuota->quota_number, (int)$splitModel->id));
    }

    // Manual sanity test: start with PO 7971085247 all in 2025 quota. Split a line into 20/10, move 10 from 2025->2026, move back 2026->2025, then delete the split. Expected: purchase_order_quota remains only 2025; quota_histories net per split is 0; KPIs unchanged.

    private function resolveTableName(string $table): ?string
    {
        if (Schema::hasTable($table)) {
            return $table;
        }
        try {
            $row = DB::selectOne("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE LOWER(TABLE_NAME) = LOWER(?)", [$table]);
            if ($row) {
                return $row->TABLE_NAME ?? $row->table_name ?? $table;
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private function columnMap(?string $table): array
    {
        if (!$table) { return []; }
        try {
            $cols = Schema::getColumnListing($table);
        } catch (\Throwable $e) {
            $cols = [];
        }
        $map = [];
        foreach ($cols as $col) {
            $map[strtolower($col)] = $col;
        }
        return $map;
    }
}
