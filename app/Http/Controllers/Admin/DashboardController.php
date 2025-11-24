<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Permission;
use App\Models\Quota;
use App\Models\Role;
use App\Models\PoHeader;
use App\Models\PoLine;
use App\Models\GrReceipt;
use App\Models\User;
use Carbon\Carbon;
use App\Support\DbExpression;

class DashboardController extends Controller
{
    public function index()
    {
        $driver = DB::connection()->getDriverName();

        // Pipeline & KPI calculations (lightweight, no mapping changes)
        $metrics = [];
        try {
            // Products mapping coverage (using HS→PK resolver for current year)
            $periodKey = now()->format('Y');
            $resolver = app(\App\Services\HsCodeResolver::class);

            $mapped = 0; $unmapped = 0;
            $rows = \App\Models\Product::query()->get(['id','hs_code','pk_capacity','code','name','sap_model']);
            foreach ($rows as $p) {
                $hs = $p->hs_code ?? null;
                if ($hs === null || $hs === '') { $unmapped++; continue; }
                $pk = $resolver->resolveForProduct($p, $periodKey);
                if ($pk === null) { $unmapped++; } else { $mapped++; }
            }
            $metrics['mapped'] = $mapped;
            $metrics['unmapped'] = $unmapped;
        } catch (\Throwable $e) {
            $metrics['mapped'] = $metrics['unmapped'] = 0; // tolerate if table pruned
        }

        // Global aggregates for Quota Pipeline (mapped HS only, exclude ACC)
        $totalPo = 0.0; $totalGr = 0.0; $totalAlloc = 0.0;
        try {
            // Base: PO lines joined with headers and HS mapping
            $totalPo = (float) DB::table('po_lines as pl')
                ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                ->whereNotNull('pl.hs_code_id')
                ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
                ->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');
            $metrics['open_po_qty'] = $totalPo; // as per new rule: show total PO
        } catch (\Throwable $e) { $metrics['open_po_qty'] = 0; }

        try {
            // Compute total GR (normalized by PO+line) with the same HS filters
            $grn = DB::table('gr_receipts')
                ->selectRaw("po_no, ".DbExpression::lineNoInt('line_no')." AS ln")
                ->selectRaw('SUM(qty) as qty')
                ->groupBy('po_no','ln');
            $totalGr = (float) DB::table('po_lines as pl')
                ->join('po_headers as ph','pl.po_header_id','=','ph.id')
                ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                ->leftJoinSub($grn, 'grn', function($j){
                    $j->on('grn.po_no','=','ph.po_number')
                      ->whereRaw("grn.ln = ".DbExpression::lineNoInt('pl.line_no'));
                })
                ->whereNotNull('pl.hs_code_id')
                ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
                ->selectRaw('SUM(COALESCE(grn.qty,0)) as s')->value('s');
            $metrics['gr_qty'] = $totalGr; // total GR all time under same filters
        } catch (\Throwable $e) { $metrics['gr_qty'] = 0; }

        // In-Transit = total_po - total_gr (clamped >= 0)
        $metrics['in_transit_qty'] = max(($metrics['open_po_qty'] ?? 0) - ($metrics['gr_qty'] ?? 0), 0);

        try {
            // GR qty (last 30 days)
            $since = now()->subDays(30)->toDateString();
            $metrics['gr_qty'] = (float) DB::table('gr_receipts')->where('receive_date','>=',$since)->sum('qty');
        } catch (\Throwable $e) { $metrics['gr_qty'] = 0; }

        // Derived per-quota KPI (quota_id-based, clamped to allocation)
        $quotaCards = collect();
        try {
            // Show all quotas (not limited by current period/year)
            $quotas = \App\Models\Quota::query()
                ->orderBy('quota_number')
                ->get();

            $ids = $quotas->pluck('id')->all();
            // Aggregate forecast/actual over full history
            $forecastByQuota = empty($ids) ? collect() : DB::table('quota_histories')
                ->select('quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
                ->where('change_type', \App\Models\QuotaHistory::TYPE_FORECAST_DECREASE)
                ->whereIn('quota_id', $ids)
                ->groupBy('quota_id')
                ->pluck('qty', 'quota_id');
            $actualByQuota = empty($ids) ? collect() : DB::table('quota_histories')
                ->select('quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
                ->where('change_type', \App\Models\QuotaHistory::TYPE_ACTUAL_DECREASE)
                ->whereIn('quota_id', $ids)
                ->groupBy('quota_id')
                ->pluck('qty', 'quota_id');

            $quotaBatches = [];
            foreach ($quotas as $q) {
                $alloc = (float) ($q->total_allocation ?? 0);
                $fc = min($alloc, (float) ($forecastByQuota[$q->id] ?? 0));
                $ac = min($alloc, (float) ($actualByQuota[$q->id] ?? 0));
                $q->setAttribute('forecast_consumed', $fc);
                $q->setAttribute('actual_consumed', $ac);
                $q->setAttribute('forecast_remaining', max($alloc - $fc, 0));
                $q->setAttribute('actual_remaining', max($alloc - $ac, 0));
                // Compute In-Transit as PO outstanding (ordered - received) matched by quota's PK bucket and period, excluding ACC
                // Robust per-quota outstanding using GR receipts; avoid exceptions and fallbacks
                $bounds = \App\Support\PkCategoryParser::parse((string) ($q->government_category ?? ''));
                // 1) Distinct list of PO numbers allocated to this quota to avoid duplication
                $poList = DB::table('purchase_order_quota as pq')
                    ->join('purchase_orders as po', 'pq.purchase_order_id', '=', 'po.id')
                    ->where('pq.quota_id', $q->id)
                    ->distinct()
                    ->pluck('po.po_number');

                if ($poList->isEmpty()) {
                    $qOutstanding = 0.0;
                } else {
                    // 2) Aggregate GR per PO+line
                    $grn = DB::table('gr_receipts')
                        ->selectRaw("po_no, ".DbExpression::lineNoInt('line_no')." AS ln")
                        ->selectRaw('SUM(qty) as qty')
                        ->groupBy('po_no','ln');

                    // 3) Sum outstanding per mapped non‑ACC line within bucket and period, for those POs only
                    $lines = DB::table('po_lines as pl')
                        ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                        ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                        ->leftJoinSub($grn, 'grn', function($j){
                            $j->on('grn.po_no','=','ph.po_number')
                              ->whereRaw("grn.ln = ".DbExpression::lineNoInt('pl.line_no'));
                        })
                        ->whereIn('ph.po_number', $poList->all())
                        ->whereNotNull('pl.hs_code_id')
                        ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'");

                    if ($bounds['min_pk'] !== null) {
                        $lines->where('hs.pk_capacity', $bounds['min_incl'] ? '>=' : '>', $bounds['min_pk']);
                    }
                    if ($bounds['max_pk'] !== null) {
                        $lines->where('hs.pk_capacity', $bounds['max_incl'] ? '<=' : '<', $bounds['max_pk']);
                    }
                    if (!empty($q->period_start) && !empty($q->period_end)) {
                        $lines->whereBetween('ph.po_date', [
                            $q->period_start->toDateString(),
                            $q->period_end->toDateString(),
                        ]);
                    }

                    $qOutstanding = (float) $lines
                        ->selectRaw('SUM(GREATEST(COALESCE(pl.qty_ordered,0) - COALESCE(grn.qty,0), 0)) as s')
                        ->value('s');
                }
                // Override In-Transit using pivot-based forecast minus actual GR
                $q->setAttribute('in_transit', max($fc - $ac, 0));
                // keep period labels from quota fields; no fixed year context

                $statusLabel = match ($q->status) {
                    \App\Models\Quota::STATUS_AVAILABLE => 'Available',
                    \App\Models\Quota::STATUS_LIMITED => 'Limited',
                    \App\Models\Quota::STATUS_DEPLETED => 'Depleted',
                    default => 'Unknown',
                };
                $quotaBatches[] = [
                    'gov_ref' => $q->quota_number,
                    'range' => (string) ($q->government_category ?? 'N/A'),
                    'forecast' => (float) $fc,
                    'actual' => (float) $ac,
                    'consumed' => (float) max($fc - $ac, 0),
                    'remaining_status' => $statusLabel,
                ];
            }

            $quotaCards = $quotas;
            // Override metrics per new business rules (use only PO/GR; ACC fully excluded via subtraction)
            // A. Collect base arrays for forecast/actual per quota
            $baseForecast = [];
            // Identify moved POs for quotas in scope (to exclude from base forecast)
            $movedPoIds = DB::table('purchase_order_quota')
                ->whereIn('quota_id', $quotas->pluck('id')->all())
                ->pluck('purchase_order_id')
                ->unique()
                ->values()
                ->all();
            $baseActual = [];
            foreach ($quotaCards as $q) {
                $alloc = (float) ($q->total_allocation ?? 0);
                $cat = (string) ($q->government_category ?? '');
                $bounds = \App\Support\PkCategoryParser::parse($cat);

                // Aggregate GR per PO+line
                $grn = DB::table('gr_receipts')
                    ->selectRaw("po_no, ".DbExpression::lineNoInt('line_no')." AS ln")
                    ->selectRaw('SUM(qty) as qty')
                    ->groupBy('po_no','ln');

                // Base builder (ALL HS)
                $baseAll = DB::table('po_lines as pl')
                    ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                    ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                    ->leftJoinSub($grn, 'grn', function($j){
                        $j->on('grn.po_no','=','ph.po_number')
                          ->whereRaw("grn.ln = ".DbExpression::lineNoInt('pl.line_no'));
                    })
                    ->whereNotNull('pl.hs_code_id');

                if ($bounds['min_pk'] !== null) {
                    $baseAll->where('hs.pk_capacity', $bounds['min_incl'] ? '>=' : '>', $bounds['min_pk']);
                }
                if ($bounds['max_pk'] !== null) {
                    $baseAll->where('hs.pk_capacity', $bounds['max_incl'] ? '<=' : '<', $bounds['max_pk']);
                }
                if (!empty($q->period_start) && !empty($q->period_end)) {
                    $baseAll->whereBetween('ph.po_date', [
                        $q->period_start->toDateString(),
                        $q->period_end->toDateString(),
                    ]);
                }

                // Forecast base uses purchase_orders join and EXCLUDES any PO that has any pivot row (any quota)
                $bf = (clone $baseAll)
                    ->join('purchase_orders as po', 'po.po_number', '=', 'ph.po_number')
                    ->whereNotExists(function($q){
                        $q->select(DB::raw('1'))
                          ->from('purchase_order_quota as pq')
                          ->whereColumn('pq.purchase_order_id', 'po.id');
                    });
                $bfAcc = (clone $bf)->whereRaw("COALESCE(UPPER(hs.hs_code),'') = 'ACC'");

                $forecast_all_po = (float) (clone $bf)->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');
                $forecast_acc_po = (float) (clone $bfAcc)->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');

                // Actual base follows GR only (do not exclude moved POs)
                $baAcc = (clone $baseAll)->whereRaw("COALESCE(UPPER(hs.hs_code),'') = 'ACC'");
                $actual_all_gr   = (float) (clone $baseAll)->selectRaw('SUM(COALESCE(grn.qty,0)) as s')->value('s');
                $actual_acc_gr   = (float) (clone $baAcc)->selectRaw('SUM(COALESCE(grn.qty,0)) as s')->value('s');

                $forecast_non_acc   = max($forecast_all_po - $forecast_acc_po, 0);
                $actual_non_acc     = max($actual_all_gr - $actual_acc_gr, 0);
                $in_transit_non_acc = max($forecast_non_acc - $actual_non_acc, 0);

                // Debug only for <8 PK 2025
                $isLess8 = stripos($cat, '<8') !== false;
                $is2025 = $q->period_start && \Carbon\Carbon::parse($q->period_start)->year === 2025;
                if ($isLess8 && $is2025) {
                    Log::info('DEBUG ACC', compact('forecast_all_po','forecast_acc_po','actual_all_gr','actual_acc_gr'));
                }
                // A. Save base values
                $baseForecast[$q->id] = (float) $forecast_non_acc;
                $baseActual[$q->id]   = (float) $actual_non_acc;

                // Set base first; will be overridden by adjusted forecast below if applicable
                $q->setAttribute('forecast_consumed', $forecast_non_acc);
                $q->setAttribute('actual_consumed', $actual_non_acc);
                $q->setAttribute('in_transit', $in_transit_non_acc);
                $q->setAttribute('forecast_remaining', max($alloc - $forecast_non_acc, 0));
                $q->setAttribute('actual_remaining', max($alloc - $actual_non_acc, 0));
            }

            // Pivot overlay per new MOVE logic: build final forecast per quota
            $forecastFromPivot = empty($quotaCards) ? collect() : DB::table('purchase_order_quota as pq')
                ->select('pq.quota_id', DB::raw('SUM(COALESCE(pq.allocated_qty,0)) as qty'))
                ->whereIn('pq.quota_id', $quotaCards->pluck('id')->all())
                ->whereExists(function($q){
                    $q->select(DB::raw('1'))
                      ->from('purchase_orders as po')
                      ->join('po_headers as ph','po.po_number','=','ph.po_number')
                      ->join('po_lines as pl','pl.po_header_id','=','ph.id')
                      ->join('hs_code_pk_mappings as hs','pl.hs_code_id','=','hs.id')
                      ->whereRaw('pq.purchase_order_id = po.id')
                      ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'");
                })
                ->groupBy('pq.quota_id')
                ->pluck('qty','pq.quota_id');

            $forecastFinal = $baseForecast;
            foreach ($quotaCards as $qq) {
                $forecastFinal[$qq->id] = (float) ($baseForecast[$qq->id] ?? 0) + (float) ($forecastFromPivot[$qq->id] ?? 0);
            }
            Log::info('PK Debug', [
                'base_no_pivot' => $baseForecast,
                'pivot'         => $forecastFromPivot,
                'final'         => $forecastFinal,
            ]);
            // Apply final KPI values using forecastFinal (actual stays base)
            $sumBaseForecast   = array_sum($baseForecast);
            $sumForecastFinal = 0.0; $sumActualFinal = 0.0; $sumInTransitFinal = 0.0;
            foreach ($quotaCards as $q) {
                $quotaId = $q->id;
                $alloc    = (float) ($q->total_allocation ?? 0);
                $forecast = (float) ($forecastFinal[$quotaId] ?? ($baseForecast[$quotaId] ?? 0.0));
                $actual   = (float) ($baseActual[$quotaId] ?? 0.0);

                $inTransit         = max($forecast - $actual, 0.0);
                $forecastRemaining = max($alloc - $forecast, 0.0);
                $actualRemaining   = max($alloc - $actual, 0.0);

                $q->setAttribute('forecast_consumed',  $forecast);
                $q->setAttribute('actual_consumed',    $actual);
                $q->setAttribute('in_transit',         $inTransit);
                $q->setAttribute('forecast_remaining', $forecastRemaining);
                $q->setAttribute('actual_remaining',   $actualRemaining);

                $sumForecastFinal += $forecast;
                $sumActualFinal   += $actual;
                $sumInTransitFinal += $inTransit;
            }

            // expose to view for charts
            $quotaBatches = $quotaBatches;
        } catch (\Throwable $e) {}

        // Activity feed (last 7 days)
        $activities = [];
        try {
            $recentImports = \App\Models\Import::where('created_at','>=', now()->subDays(7))
                ->orderByDesc('created_at')->limit(10)->get();
            foreach ($recentImports as $im){
                $activities[] = [
                    'type' => $im->type,
                    'title' => sprintf('%s: %s (valid %d / error %d)', strtoupper($im->type), $im->source_filename, (int)($im->valid_rows ?? 0), (int)($im->error_rows ?? 0)),
                    'time' => $im->created_at->diffForHumans(),
                ];
            }
        } catch (\Throwable $e) {}

        // Alerts
        $alerts = [];
        try {
            // PO tanpa mapping (hs_code_id null)
            $unmappedPo = (int) DB::table('po_lines')->whereNull('hs_code_id')->count();
            if ($unmappedPo > 0) {
                $alerts[] = $unmappedPo === 1
                    ? '1 PO line without HS mapping'
                    : ($unmappedPo.' PO lines without HS mapping');
            }
        } catch (\Throwable $e) {}
        try {
            $lowQuota = \App\Models\Quota::whereColumn('forecast_remaining','<=', DB::raw('total_allocation * 0.15'))->count();
            if ($lowQuota > 0) {
                $alerts[] = $lowQuota === 1
                    ? '1 PK bucket nearing depletion (<15%)'
                    : ($lowQuota.' PK buckets nearing depletion (<15%)');
            }
        } catch (\Throwable $e) {}
        try {
            $overGr = (int) DB::table('po_lines')->whereColumn('qty_received','>','qty_ordered')->count();
            if ($overGr > 0) {
                $alerts[] = $overGr === 1
                    ? '1 line: GR exceeds ordered (needs audit)'
                    : ($overGr.' lines: GR exceeds ordered (needs audit)');
            }
        } catch (\Throwable $e) {}
        // Statistics
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        
        // Count admins and regular users
        $totalAdmins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->count();
        
        $totalRegularUsers = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->count();
        
        $totalRoles = Role::count();
        $totalPermissions = Permission::count();

        // Quota insights (large tile uses the new rule)
        try { $totalAlloc = (float) Quota::sum('total_allocation'); } catch (\Throwable $e) { $totalAlloc = 0.0; }

        // Quota insights (exclude ACC)
        $totalForecastConsumed = (float) DB::table('purchase_order_quota as pq')
            ->whereExists(function($q){
                $q->select(DB::raw('1'))
                  ->from('purchase_orders as po')
                  ->join('po_headers as ph','po.po_number','=','ph.po_number')
                  ->join('po_lines as pl','pl.po_header_id','=','ph.id')
                  ->join('hs_code_pk_mappings as hs','pl.hs_code_id','=','hs.id')
                  ->whereRaw('pq.purchase_order_id = po.id')
                  ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'");
            })
            ->sum('allocated_qty');

        $totalActualConsumed = (float) DB::table('gr_receipts as gr')
            ->join('po_headers as ph','ph.po_number','=','gr.po_no')
            ->join('po_lines as pl', function($j){
                $j->on('pl.po_header_id','=','ph.id')
                  ->whereRaw(DbExpression::lineNoTrimmed('pl.line_no').' = '.DbExpression::lineNoTrimmed('gr.line_no'));
            })
            ->join('hs_code_pk_mappings as hs','pl.hs_code_id','=','hs.id')
            ->join('purchase_orders as po','po.po_number','=','ph.po_number')
            ->join('purchase_order_quota as pq','pq.purchase_order_id','=','po.id')
            ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
            ->select(DB::raw('SUM(gr.qty) as s'))
            ->value('s');

        $quotaStats = [
            'total' => Quota::count(),
            'available' => Quota::where('status', Quota::STATUS_AVAILABLE)->count(),
            'limited' => Quota::where('status', Quota::STATUS_LIMITED)->count(),
            'depleted' => Quota::where('status', Quota::STATUS_DEPLETED)->count(),
            'forecast_remaining' => max($totalAlloc - $totalForecastConsumed, 0),
            'actual_remaining' => max($totalAlloc - $totalActualConsumed, 0),
        ];

        // Purchase order insights
        $currentPeriodStart = Carbon::now()->startOfMonth();
        $currentPeriodEnd = Carbon::now()->endOfMonth();

        $poStats = [
            'this_month' => PoHeader::whereBetween('po_date', [$currentPeriodStart, $currentPeriodEnd])->count(),
            'need_shipment' => PoLine::whereColumn('qty_received', '<', 'qty_ordered')->count(),
            'in_transit' => PoLine::where('qty_received', '>', 0)->whereColumn('qty_received', '<', 'qty_ordered')->count(),
            'completed' => PoLine::where('qty_ordered', '>', 0)->whereColumn('qty_received', '>=', 'qty_ordered')->count(),
        ];

        $recentPurchaseOrders = PoHeader::with(['lines' => function ($query) {
                $query->orderBy('line_no');
            }])
            ->orderByDesc('po_date')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function (PoHeader $header) {
                $totalQty = (float) $header->lines->sum('qty_ordered');
                $receivedQty = (float) $header->lines->sum('qty_received');

                $statusKey = 'draft';
                if ($totalQty <= 0) {
                    $statusKey = 'draft';
                } elseif ($receivedQty >= $totalQty) {
                    $statusKey = 'completed';
                } elseif ($receivedQty > 0) {
                    $statusKey = 'partial';
                } else {
                    $statusKey = 'ordered';
                }

                return (object) [
                    'po_number' => $header->po_number,
                    'po_date' => $header->po_date,
                    'supplier' => $header->supplier,
                    'line_count' => $header->lines->count(),
                    'total_qty' => $totalQty,
                    'received_qty' => $receivedQty,
                    'status_key' => $statusKey,
                    'sap_statuses' => $header->lines->pluck('sap_order_status')->filter()->unique()->values()->all(),
                ];
            });

        // Shipment insights (derived from PO lines & GR receipts)
        $shipmentStats = [
            'in_transit' => $poStats['in_transit'],
            'delivered' => GrReceipt::count(),
            'pending_receipt' => $poStats['need_shipment'],
        ];

        // Consolidated summary tiles
        $poAggregate = PoLine::selectRaw('SUM(qty_ordered) as ordered_total, SUM(qty_received) as received_total')->first();
        $orderedTotal = (float) ($poAggregate->ordered_total ?? 0);
        $receivedTotal = (float) ($poAggregate->received_total ?? 0);
        $summary = [
            'po_total' => PoHeader::count(),
            'po_ordered_total' => $orderedTotal,
            'po_outstanding_total' => max($orderedTotal - $receivedTotal, 0),
            'gr_total_qty' => (float) DB::table('gr_receipts')->sum('qty'),
            'gr_document_total' => (int) DB::table('gr_receipts')
                ->selectRaw(
                    $driver === 'sqlsrv'
                        ? "COUNT(DISTINCT COALESCE(gr_unique, CONCAT(po_no,'|',line_no,'|', CONVERT(varchar(50), receive_date, 126)))) as total"
                        : "COUNT(DISTINCT COALESCE(gr_unique, po_no || '|' || line_no || '|' || receive_date::text)) as total"
                )
                ->value('total'),
            'quota_total_allocation' => (float) Quota::sum('total_allocation'),
            'quota_total_remaining' => (float) Quota::sum('actual_remaining'),
        ];

        $recentShipments = GrReceipt::orderByDesc('receive_date')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function (GrReceipt $receipt) {
                // Shape data to match admin.dashboard view expectations
                return (object) [
                    'po_number'      => $receipt->po_no,
                    'line_no'        => $receipt->line_no,
                    'receive_date'   => $receipt->receive_date,
                    'item_name'      => $receipt->item_name,
                    'vendor_name'    => $receipt->vendor_name,
                    'warehouse_name' => $receipt->wh_name,
                    'sap_status'     => $receipt->cat_po, // fallback handled in view
                    'quantity'       => (float) $receipt->qty,
                ];
            });

        // Optional no-cache response (diagnostic only). Uncomment to disable browser caching while styling.
        // return response()->view('admin.dashboard', compact(
        //     'totalUsers', 'activeUsers', 'inactiveUsers', 'totalAdmins', 'totalRegularUsers', 'totalRoles', 'totalPermissions',
        //     'recentUsers', 'usersByRole', 'quotaStats', 'quotaAlerts', 'recentQuotaHistories', 'poStats', 'recentPurchaseOrders',
        //     'shipmentStats', 'recentShipments'
        // ))->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        //   ->header('Pragma', 'no-cache')
        //   ->header('Expires', '0');

        $viewName = \Illuminate\Support\Facades\View::exists('admin.dashboard')
            ? 'admin.dashboard'
            : (\Illuminate\Support\Facades\View::exists('dashboard.index') ? 'dashboard.index' : 'dashboard');

        return view($viewName, compact(
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'totalAdmins',
            'totalRegularUsers',
            'totalRoles',
            'totalPermissions',
            'quotaStats',
            'poStats',
            'recentPurchaseOrders',
            'shipmentStats',
            'recentShipments',
            'metrics',
            'quotaCards',
            'quotaBatches',
            'activities',
            'alerts',
            'summary'
        ));
    }
}
