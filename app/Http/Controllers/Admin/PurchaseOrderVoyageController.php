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

        // Compose vendor label and append distinct factories if available
        $vendorName = $headers->pluck('supplier')->filter()->unique()->implode(', ');
        $factories = DB::table('po_lines as pl')
            ->join('po_headers as ph','pl.po_header_id','=','ph.id')
            ->where('ph.po_number', $poNumber)
            ->whereNotNull('pl.voyage_factory')
            ->whereRaw("NULLIF(pl.voyage_factory,'') <> ''")
            ->selectRaw("STRING_AGG(DISTINCT pl.voyage_factory, ', ') as fs")
            ->value('fs');
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
        $poRecord = DB::table('purchase_orders')->where('po_number', $poNumber)->first();
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

        return view('admin.purchase_orders.voyage', compact('summary', 'lines', 'poNumber', 'splitsByLine','quotaOptionsByLine','sourceQuotaByLine'));
    }

    public function bulkUpdate(Request $request, string $po): RedirectResponse
    {
        $payload = $request->validate([
            'rows' => ['sometimes','array'],
            'rows.*.line_id' => ['required','integer','exists:po_lines,id'],
            'rows.*.bl' => ['nullable','string','max:100'],
            'rows.*.factory' => ['nullable','string','max:100'],
            'rows.*.status' => ['nullable','string','max:50'],
            'rows.*.etd' => ['nullable','date'],
            'rows.*.eta' => ['nullable','date'],
            'rows.*.remark' => ['nullable','string','max:500'],
            // optional split rows (insert/update/delete) — can be passed as JSON via splits_json
            'splits' => ['sometimes','array'],
            'splits.*.id' => ['nullable','integer'],
            'splits.*.line_id' => ['required_without:splits.*.id','integer','exists:po_lines,id'],
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

        DB::transaction(function () use ($rows, $splits, &$saved) {
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
                    // Build base data; avoid overriding qty/seq_no unless provided
                    $data = [
                        'po_line_id' => (int) ($sp['line_id'] ?? 0),
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
                    // Only update qty when explicitly provided
                    if (array_key_exists('qty', $sp)) {
                        $q = (float) $sp['qty'];
                        if ($sid > 0) {
                            if ($q > 0) { $data['qty'] = $q; }
                        } else {
                            $data['qty'] = max($q, 0);
                        }
                    }
                    // normalize split dates
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
                        DB::table('po_line_voyage_splits')->where('id', $sid)->delete();
                        continue;
                    }
                    if ($sid > 0) {
                        DB::table('po_line_voyage_splits')->where('id', $sid)->update($data);
                    } else {
                        // For insert: require qty present and > 0
                        if (!array_key_exists('qty', $sp) || (float) $sp['qty'] <= 0) {
                            continue;
                        }
                        if (!array_key_exists('seq_no', $sp)) { $data['seq_no'] = 1; }
                        $data['created_at'] = now();
                        $data['created_by'] = auth()->id();
                        DB::table('po_line_voyage_splits')->insert($data);
                    }
                }
            }
        });

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
            'source_quota_id' => ['required','integer','exists:quotas,id'],
            'target_quota_id' => ['required','integer','exists:quotas,id','different:source_quota_id'],
            'move_qty' => ['nullable','numeric','min:1'],
            'eta_date' => ['nullable','date'],
        ]);

        // Verify line belongs to PO number in route
        $poHeader = DB::table('po_headers')->where('po_number', $po)->first();
        abort_unless($poHeader, 404);
        $line = DB::table('po_lines')->where('id', $data['line_id'])->where('po_header_id', $poHeader->id)->first();
        abort_unless($line, 404);
        $split = DB::table('po_line_voyage_splits')->where('id', $data['split_id'])->where('po_line_id', $line->id)->first();
        abort_unless($split, 404);

        $poModel = \App\Models\PurchaseOrder::where('po_number', $po)->first();
        if (!$poModel) {
            // Create a minimal PO record to satisfy references if needed
            $prod = \App\Models\Product::query()->firstOrCreate(['code' => (string)($line->model_code ?? 'UNKNOWN')], [
                'name' => (string)($line->model_code ?? 'UNKNOWN'),
                'sap_model' => (string)($line->model_code ?? 'UNKNOWN'),
                'is_active' => true,
            ]);
            $poModel = \App\Models\PurchaseOrder::create([
                'po_number' => (string) $po,
                'product_id' => $prod->id,
                'quantity' => (int) max((int)($line->qty_ordered ?? 0), 0),
                'order_date' => $poHeader->po_date ?? now()->toDateString(),
                'vendor_name' => (string) ($poHeader->supplier ?? ''),
                'status' => \App\Models\PurchaseOrder::STATUS_ORDERED,
                'plant_name' => 'Voyage',
                'plant_detail' => 'Voyage Move Quota',
            ]);
        }

        $source = \App\Models\Quota::lockForUpdate()->findOrFail((int)$data['source_quota_id']);
        $target = \App\Models\Quota::lockForUpdate()->findOrFail((int)$data['target_quota_id']);

        $eta = $data['eta_date'] ?? ($split->voyage_eta ?? null);
        $occurredOn = $eta ? new \DateTimeImmutable((string)$eta) : null;
        $userId = auth()->id();

        // Qty default to split qty
        $qty = (float) ($data['move_qty'] ?? $split->qty ?? 0);
        $qty = max(0, (float)$qty);
        if ($qty <= 0) { return back()->withErrors(['move_qty' => 'Quantity to move must be > 0']); }

        DB::transaction(function () use ($poModel, $source, $target, $qty, $occurredOn, $userId) {
            // Refund forecast on source
            $source->incrementForecast((int)$qty, 'Voyage split reallocation (refund)', $poModel, $occurredOn, $userId);
            // Reserve on target
            $target->decrementForecast((int)$qty, 'Voyage split reallocation (reserve)', $poModel, $occurredOn, $userId);

            // Pivot update
            $pivotSrc = DB::table('purchase_order_quota')
                ->where('purchase_order_id', $poModel->id)
                ->where('quota_id', $source->id)
                ->first();
            if ($pivotSrc) {
                $newAlloc = max(0, (int)$pivotSrc->allocated_qty - (int)$qty);
                if ($newAlloc > 0) {
                    DB::table('purchase_order_quota')->where('id', $pivotSrc->id)->update(['allocated_qty' => $newAlloc, 'updated_at' => now()]);
                } else {
                    DB::table('purchase_order_quota')->where('id', $pivotSrc->id)->delete();
                }
            }
            $pivotTgt = DB::table('purchase_order_quota')
                ->where('purchase_order_id', $poModel->id)
                ->where('quota_id', $target->id)
                ->first();
            if ($pivotTgt) {
                DB::table('purchase_order_quota')->where('id', $pivotTgt->id)->update(['allocated_qty' => (int)$pivotTgt->allocated_qty + (int)$qty, 'updated_at' => now()]);
            } else {
                DB::table('purchase_order_quota')->insert([
                    'purchase_order_id' => $poModel->id,
                    'quota_id' => $target->id,
                    'allocated_qty' => (int) $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', sprintf('Moved %s units (forecast) from quota %s to %s for split #%d.', number_format($qty), $source->quota_number, $target->quota_number, (int)$split->id));
    }
}
