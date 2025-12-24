<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quota;
use App\Models\SapPurchaseOrderAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

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

                $q = DB::table($purchaseOrdersTable)
                    ->where($poDocCol, $poNumber)
                    ->select(array_filter([
                        DB::raw('id as id'),
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

                $allocationMetaByLine = [];
                $voyageSlicesByLine = [];

                $lines->setCollection(
                    $lines->getCollection()->map(function ($ln) use (
                        &$allocationMetaByLine,
                        &$voyageSlicesByLine,
                        $poNumber,
                        $summary
                    ) {
                        $lineNoKey = $this->normalizeLineKey($ln->line_no ?? '');
                        $targetQty = (float) ($ln->qty_ordered ?? 0);
                        $periodKey = $this->deriveLinePeriodKey($ln->delivery_date ?? null, $ln->eta ?? null, $ln->etd ?? null);

                        $allocation = $this->resolveVoyageAllocation($poNumber, $lineNoKey, [
                            'po_line_no_raw' => $ln->line_no ?? null,
                            'item_code' => $ln->material ?? null,
                            'vendor_no' => $summary['vendor_number'] ?? null,
                            'vendor_name' => $summary['vendor_name'] ?? null,
                            'order_date' => $ln->delivery_date ?? null,
                            'target_qty' => $targetQty,
                            'period_key' => $periodKey,
                        ]);

                        $slices = $this->normalizeVoyageSlicesForLine($allocation, $targetQty, $periodKey);
                        $voyageSlicesByLine[$ln->id] = $slices;
                        $allocationMetaByLine[$ln->id] = [
                            'allocation_id' => $allocation->id,
                            'po_doc' => $poNumber,
                            'line_no' => $lineNoKey,
                            'period_key' => $allocation->period_key ?? $periodKey,
                            'target_qty' => (float) ($allocation->target_qty ?? $targetQty),
                            'base_slice_id' => $slices['base']['slice_id'] ?? null,
                            'base_quota_id' => $slices['base']['quota_id'] ?? null,
                        ];

                        $ln->qty_remaining = max((float) ($slices['base']['qty'] ?? $targetQty), 0);
                        $ln->voyage_period_key = $allocationMetaByLine[$ln->id]['period_key'];

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

        $this->attachQuotaLabelsToSlices($voyageSlicesByLine, $sourceQuotaByLine, $allQuotas);

        return view('admin.purchase_orders.voyage', compact('summary', 'lines', 'poNumber', 'voyageSlicesByLine','quotaOptionsByLine','sourceQuotaByLine','allocationMetaByLine'))
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

        $allocationMetaByLine = [];
        $voyageSlicesByLine = [];

        $lines->setCollection(
            $lines->getCollection()->map(function ($ln) use (&$allocationMetaByLine, &$voyageSlicesByLine, $poNumber, $summary) {
                $lineNoKey = $this->normalizeLineKey($ln->line_no ?? '');
                $targetQty = (float) ($ln->qty_ordered ?? 0);
                $periodKey = $this->deriveLinePeriodKey($ln->delivery_date ?? null, $ln->eta ?? null, $ln->etd ?? null);

                $allocation = $this->resolveVoyageAllocation($poNumber, $lineNoKey, [
                    'po_line_no_raw' => $ln->line_no ?? null,
                    'item_code' => $ln->material ?? null,
                    'vendor_no' => $summary['vendor_number'] ?? null,
                    'vendor_name' => $summary['vendor_name'] ?? null,
                    'order_date' => $ln->delivery_date ?? null,
                    'target_qty' => $targetQty,
                    'period_key' => $periodKey,
                ]);

                $slices = $this->normalizeVoyageSlicesForLine($allocation, $targetQty, $periodKey);
                $voyageSlicesByLine[$ln->id] = $slices;
                $allocationMetaByLine[$ln->id] = [
                    'allocation_id' => $allocation->id,
                    'po_doc' => $poNumber,
                    'line_no' => $lineNoKey,
                    'period_key' => $allocation->period_key ?? $periodKey,
                    'target_qty' => (float) ($allocation->target_qty ?? $targetQty),
                    'base_slice_id' => $slices['base']['slice_id'] ?? null,
                ];

                $ln->qty_remaining = max((float) ($slices['base']['qty'] ?? $targetQty), 0);
                $ln->voyage_period_key = $allocationMetaByLine[$ln->id]['period_key'];

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

        $this->attachQuotaLabelsToSlices($voyageSlicesByLine, $sourceQuotaByLine, $allQuotas);

        return view('admin.purchase_orders.voyage', compact('summary', 'lines', 'poNumber', 'voyageSlicesByLine','quotaOptionsByLine','sourceQuotaByLine','allocationMetaByLine'))
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

        $payload = $request->validate([
            'rows' => ['sometimes','array'],
            'rows.*.line_id' => $lineExistsRule,
            'rows.*.bl' => ['nullable','string','max:100'],
            'rows.*.factory' => ['nullable','string','max:100'],
            'rows.*.status' => ['nullable','string','max:50'],
            'rows.*.etd' => ['nullable','date'],
            'rows.*.eta' => ['nullable','date'],
            'rows.*.remark' => ['nullable','string','max:500'],
            'allocations' => ['sometimes','array'],
            'allocations.*.allocation_id' => ['nullable','integer','exists:sap_purchase_order_allocations,id'],
            'allocations.*.po_doc' => ['required_with:allocations.*.line_no','string','max:50'],
            'allocations.*.line_no' => ['required_with:allocations.*.po_doc','string','max:30'],
            'allocations.*.period_key' => ['nullable','string','max:7'],
            'allocations.*.target_qty' => ['nullable','numeric'],
            'allocations.*.slices' => ['sometimes','array'],
            'allocations_json' => ['sometimes','string'],
            'rows_json' => ['sometimes','string'],
        ]);

        // rows may come as array or JSON (rows_json)
        $rows = $payload['rows'] ?? [];
        if (empty($rows) && $request->filled('rows_json')) {
            try { $rows = json_decode((string) $request->input('rows_json'), true) ?: []; } catch (\Throwable $e) { $rows = []; }
        }
        $saved = 0;
        $allocationPayloads = $payload['allocations'] ?? [];
        if (empty($allocationPayloads) && $request->filled('allocations_json')) {
            try { $allocationPayloads = json_decode((string) $request->input('allocations_json'), true) ?: []; } catch (\Throwable $e) { $allocationPayloads = []; }
        }

        DB::transaction(function () use ($rows, $allocationPayloads, &$saved, $poNumber, $usingPurchaseOrders, $purchaseOrdersTable, $poDocCol, $purchaseOrderColumns) {
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
                // Persist voyage allocations
                foreach ($allocationPayloads as $allocPayload) {
                    if ($this->persistVoyageAllocation($poNumber, $allocPayload)) {
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

            foreach ($allocationPayloads as $allocPayload) {
                if ($this->persistVoyageAllocation($poNumber, $allocPayload)) {
                    $saved++;
                }
            }
        });

        // Enrich audit log with concise counters so labels are clearer
        try {
            $request->attributes->set('audit_extra', [
                'saved_rows' => (int) $saved,
                'rows_count' => is_array($rows) ? count($rows) : 0,
                'allocations_count' => is_array($allocationPayloads) ? count($allocationPayloads) : 0,
            ]);
        } catch (\Throwable $e) { /* ignore */ }

        if ($request->wantsJson()) {
            return back()->with('status', "Saved: $saved rows");
        }
        return back()->with('status', "Saved: $saved rows");
    }

    private function resolveVoyageAllocation(string $poNumber, string $lineNoKey, array $context = []): SapPurchaseOrderAllocation
    {
        $poNumber = trim($poNumber);
        $lineNoKey = $lineNoKey !== '' ? $lineNoKey : '0';

        $allocation = SapPurchaseOrderAllocation::firstOrNew([
            'po_doc' => $poNumber,
            'po_line_no' => $lineNoKey,
        ]);

        foreach ([
            'po_line_no_raw' => 'po_line_no_raw',
            'item_code' => 'item_code',
            'vendor_no' => 'vendor_no',
            'vendor_name' => 'vendor_name',
        ] as $ctxKey => $attr) {
            if (array_key_exists($ctxKey, $context) && $context[$ctxKey] !== null && $context[$ctxKey] !== '') {
                $allocation->{$attr} = (string) $context[$ctxKey];
            }
        }

        if (array_key_exists('target_qty', $context) && $context['target_qty'] !== null) {
            $allocation->target_qty = (float) $context['target_qty'];
        }

        if (array_key_exists('order_date', $context)) {
            $orderDate = $this->normalizeDateValue($context['order_date']);
            if ($orderDate) {
                $allocation->order_date = $orderDate;
            }
        }

        $periodKey = $this->normalizePeriodKey($context['period_key'] ?? null);
        if ($periodKey) {
            $allocation->period_key = $periodKey;
        }

        $allocations = $allocation->allocations ?? [];
        if (!is_array($allocations)) {
            $allocations = [];
        }
        $voyage = $allocations['voyage'] ?? [];
        if (!is_array($voyage)) {
            $voyage = [];
        }
        if (!isset($voyage['period_key']) || !$voyage['period_key']) {
            $voyage['period_key'] = $allocation->period_key ?? $periodKey;
        }
        if (!isset($voyage['slices']) || !is_array($voyage['slices'])) {
            $voyage['slices'] = [];
        }
        $allocations['voyage'] = $voyage;
        $allocation->allocations = $allocations;
        $allocation->last_seen_at = now();
        if (!$allocation->exists) {
            $allocation->is_active = true;
        }
        $allocation->save();

        return $allocation;
    }

    private function normalizeVoyageSlicesForLine(SapPurchaseOrderAllocation $allocation, float $targetQty, ?string $defaultPeriodKey = null): array
    {
        $bucket = $allocation->getVoyageBucket();
        $periodKey = $this->normalizePeriodKey($bucket['period_key'] ?? $allocation->period_key ?? $defaultPeriodKey);
        $rawSlices = is_array($bucket['slices'] ?? null) ? $bucket['slices'] : [];
        $children = [];
        $total = 0.0;

        foreach ($rawSlices as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $slice = $this->normalizeVoyageSliceState($raw, $periodKey);
            if (!$slice) {
                continue;
            }
            $children[] = $slice;
            $total += $slice['qty'];
        }

        usort($children, function ($a, $b) {
            $seqA = $a['seq'] ?? 0;
            $seqB = $b['seq'] ?? 0;
            if ($seqA === $seqB) {
                return strcmp($a['slice_id'] ?? '', $b['slice_id'] ?? '');
            }
            return $seqA <=> $seqB;
        });

        foreach ($children as $index => &$child) {
            $child['seq'] = $index + 1;
        }
        unset($child);

        $baseQty = max(0, round($targetQty - $total, 4));

        return [
            'period_key' => $periodKey,
            'base' => [
                'slice_id' => 'base-'.$allocation->id,
                'qty' => $baseQty,
                'period_key' => $periodKey,
            ],
            'children' => $children,
        ];
    }

    private function normalizeVoyageSliceState(array $raw, ?string $defaultPeriodKey): ?array
    {
        $qty = isset($raw['qty']) ? (float) $raw['qty'] : 0.0;
        if ($qty <= 0) {
            return null;
        }
        $sliceId = trim((string) ($raw['slice_id'] ?? ''));
        if ($sliceId === '') {
            $sliceId = (string) Str::uuid();
        }
        return [
            'slice_id' => $sliceId,
            'qty' => round($qty, 4),
            'period_key' => $this->normalizePeriodKey($raw['period_key'] ?? $defaultPeriodKey),
            'quota_id' => isset($raw['quota_id']) && (int) $raw['quota_id'] > 0 ? (int) $raw['quota_id'] : null,
            'status' => $this->sanitizeSliceString($raw['status'] ?? null, 50),
            'bl' => $this->sanitizeSliceString($raw['bl'] ?? null, 100),
            'etd' => $this->normalizeDateValue($raw['etd'] ?? null),
            'eta' => $this->normalizeDateValue($raw['eta'] ?? null),
            'factory' => $this->sanitizeSliceString($raw['factory'] ?? null, 100),
            'remark' => $this->sanitizeSliceString($raw['remark'] ?? null, 500),
            'seq' => isset($raw['seq']) ? (int) $raw['seq'] : null,
        ];
    }

    private function attachQuotaLabelsToSlices(array &$voyageSlicesByLine, array $sourceQuotaByLine, $quotaCollection): void
    {
        $labelIndex = [];
        if ($quotaCollection instanceof \Illuminate\Support\Collection) {
            $labelIndex = $quotaCollection->mapWithKeys(function (Quota $quota) {
                return [$quota->id => $this->formatQuotaLabel($quota)];
            })->all();
        } elseif (is_iterable($quotaCollection)) {
            foreach ($quotaCollection as $quota) {
                if ($quota instanceof Quota) {
                    $labelIndex[$quota->id] = $this->formatQuotaLabel($quota);
                }
            }
        }

        foreach ($voyageSlicesByLine as $lineId => &$sliceData) {
            if (empty($sliceData['children']) || !is_array($sliceData['children'])) {
                continue;
            }
            foreach ($sliceData['children'] as &$slice) {
                $quotaId = $slice['quota_id'] ?? null;
                $slice['quota_label'] = $quotaId && isset($labelIndex[$quotaId])
                    ? $labelIndex[$quotaId]
                    : ($sourceQuotaByLine[$lineId]['label'] ?? 'Quota not linked');
            }
        }
        unset($sliceData, $slice);
    }

    private function persistVoyageAllocation(string $poNumber, array $payload): bool
    {
        $lineNoKey = $this->normalizeLineKey($payload['line_no'] ?? '');
        if ($lineNoKey === '') {
            return false;
        }

        $poDoc = trim((string) ($payload['po_doc'] ?? $poNumber));
        if ($poDoc === '' || strcasecmp($poDoc, $poNumber) !== 0) {
            return false;
        }

        $periodKey = $this->normalizePeriodKey($payload['period_key'] ?? null);
        $targetQty = array_key_exists('target_qty', $payload) && $payload['target_qty'] !== null
            ? (float) $payload['target_qty']
            : null;

        $allocation = SapPurchaseOrderAllocation::where('po_doc', $poNumber)
            ->where('po_line_no', $lineNoKey)
            ->lockForUpdate()
            ->first();

        if (!$allocation) {
            $allocation = $this->resolveVoyageAllocation($poNumber, $lineNoKey, [
                'po_line_no_raw' => $payload['line_no'] ?? null,
                'target_qty' => $targetQty,
                'period_key' => $periodKey,
            ]);
            $allocation = SapPurchaseOrderAllocation::where('po_doc', $poNumber)
                ->where('po_line_no', $lineNoKey)
                ->lockForUpdate()
                ->first();
        }

        if (!$allocation) {
            return false;
        }

        $existingBucket = $allocation->getVoyageBucket();
        $defaultPeriodKey = $periodKey ?? ($existingBucket['period_key'] ?? $allocation->period_key ?? null);
        $incomingSlices = is_array($payload['slices'] ?? null) ? $payload['slices'] : [];
        $normalized = [];
        $position = 1;
        foreach ($incomingSlices as $slicePayload) {
            if (!empty($slicePayload['_delete'])) {
                continue;
            }
            $slice = $this->normalizeVoyageSlicePayload($slicePayload, $defaultPeriodKey, $position);
            if (!$slice) {
                continue;
            }
            $normalized[] = $slice;
            $position++;
        }

        $nextBucket = [
            'period_key' => $periodKey ?? $defaultPeriodKey,
            'slices' => $normalized,
        ];

        $changed = !$this->voyageBucketsEqual($existingBucket, $nextBucket);

        if ($targetQty !== null && (float) $allocation->target_qty !== (float) $targetQty) {
            $allocation->target_qty = $targetQty;
            $changed = true;
        }

        if ($periodKey && $allocation->period_key !== $periodKey) {
            $allocation->period_key = $periodKey;
            $changed = true;
        }

        if (!$changed) {
            return false;
        }

        $allocation->setVoyageBucket($nextBucket);
        $allocation->last_seen_at = now();
        $allocation->save();

        return true;
    }

    private function normalizeVoyageSlicePayload(array $slicePayload, ?string $defaultPeriodKey, int $position): ?array
    {
        $qty = isset($slicePayload['qty']) ? (float) $slicePayload['qty'] : 0.0;
        if ($qty <= 0) {
            return null;
        }

        $sliceId = trim((string) ($slicePayload['slice_id'] ?? ''));
        if ($sliceId === '') {
            $sliceId = (string) Str::uuid();
        }

        return [
            'slice_id' => $sliceId,
            'seq' => $position,
            'qty' => round($qty, 4),
            'period_key' => $this->normalizePeriodKey($slicePayload['period_key'] ?? $defaultPeriodKey),
            'quota_id' => isset($slicePayload['quota_id']) && (int) $slicePayload['quota_id'] > 0 ? (int) $slicePayload['quota_id'] : null,
            'status' => $this->sanitizeSliceString($slicePayload['status'] ?? null, 50),
            'bl' => $this->sanitizeSliceString($slicePayload['bl'] ?? null, 100),
            'etd' => $this->normalizeDateValue($slicePayload['etd'] ?? null),
            'eta' => $this->normalizeDateValue($slicePayload['eta'] ?? null),
            'factory' => $this->sanitizeSliceString($slicePayload['factory'] ?? null, 100),
            'remark' => $this->sanitizeSliceString($slicePayload['remark'] ?? null, 500),
        ];
    }

    private function voyageBucketsEqual(?array $current, array $next): bool
    {
        return $this->canonicalizeVoyageBucket($current ?? []) === $this->canonicalizeVoyageBucket($next);
    }

    private function canonicalizeVoyageBucket(array $bucket): string
    {
        $periodKey = $this->normalizePeriodKey($bucket['period_key'] ?? null);
        $slices = [];
        if (isset($bucket['slices']) && is_array($bucket['slices'])) {
            foreach ($bucket['slices'] as $slice) {
                if (!is_array($slice)) {
                    continue;
                }
                $slices[] = [
                    'slice_id' => (string) ($slice['slice_id'] ?? ''),
                    'seq' => (int) ($slice['seq'] ?? 0),
                    'qty' => round((float) ($slice['qty'] ?? 0), 4),
                    'period_key' => $this->normalizePeriodKey($slice['period_key'] ?? $periodKey),
                    'quota_id' => isset($slice['quota_id']) && (int) $slice['quota_id'] > 0 ? (int) $slice['quota_id'] : null,
                    'status' => $this->sanitizeSliceString($slice['status'] ?? null, 50),
                    'bl' => $this->sanitizeSliceString($slice['bl'] ?? null, 100),
                    'etd' => $this->normalizeDateValue($slice['etd'] ?? null),
                    'eta' => $this->normalizeDateValue($slice['eta'] ?? null),
                    'factory' => $this->sanitizeSliceString($slice['factory'] ?? null, 100),
                    'remark' => $this->sanitizeSliceString($slice['remark'] ?? null, 500),
                ];
            }
        }

        usort($slices, function ($a, $b) {
            if ($a['seq'] === $b['seq']) {
                return strcmp($a['slice_id'], $b['slice_id']);
            }
            return $a['seq'] <=> $b['seq'];
        });

        return json_encode(['period_key' => $periodKey, 'slices' => $slices]);
    }

    private function deriveLinePeriodKey($deliveryDate = null, $etaDate = null, $etdDate = null): ?string
    {
        foreach ([$etaDate, $deliveryDate, $etdDate] as $candidate) {
            $key = $this->normalizePeriodKey($candidate);
            if ($key) {
                return $key;
            }
        }
        return now()->format('Y-m');
    }

    private function normalizePeriodKey($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $string)) {
            return $string;
        }
        try {
            return Carbon::parse($string)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeDateValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $string, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        try {
            return Carbon::parse($string)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function sanitizeSliceString($value, int $maxLength = 255): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }
        return mb_substr($string, 0, $maxLength);
    }

    private function formatQuotaLabel(Quota $quota): string
    {
        $number = trim((string) ($quota->quota_number ?? $quota->display_number ?? ''));
        if ($number === '') {
            $number = sprintf('QUOTA-%06d', (int) $quota->id);
        }
        $start = $quota->period_start ? $quota->period_start->format('Y-m-d') : '-';
        $end = $quota->period_end ? $quota->period_end->format('Y-m-d') : '-';

        return sprintf('%s (%s..%s)', $number, $start, $end);
    }


    private function normalizeLineKey($value): string
    {
        $trim = trim((string) $value);
        if ($trim === '') {
            return '';
        }

        if (is_numeric($trim)) {
            return (string) (int) round((float) $trim);
        }

        return $trim;
    }

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

