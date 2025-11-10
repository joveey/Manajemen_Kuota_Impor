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

        return view('admin.purchase_orders.voyage', compact('summary', 'lines', 'poNumber', 'splitsByLine'));
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
                    $data = [
                        'po_line_id' => (int) ($sp['line_id'] ?? 0),
                        'seq_no' => (int) max((int) ($sp['seq_no'] ?? 1), 1),
                        'qty' => (float) ($sp['qty'] ?? 0),
                        'voyage_bl' => ($sp['bl'] ?? '') !== '' ? trim((string)$sp['bl']) : null,
                        'voyage_etd' => null,
                        'voyage_eta' => null,
                        'voyage_factory' => ($sp['factory'] ?? '') !== '' ? trim((string)$sp['factory']) : null,
                        'voyage_status' => ($sp['status'] ?? '') !== '' ? trim((string)$sp['status']) : null,
                        'voyage_remark' => ($sp['remark'] ?? '') !== '' ? trim((string)$sp['remark']) : null,
                        'updated_at' => now(),
                    ];
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
}
