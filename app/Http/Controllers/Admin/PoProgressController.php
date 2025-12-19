<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PoProgressController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) ($request->integer('per_page') ?: 10);
        $perPage = max(5, min($perPage, 50));

        $hasPoHeaders = false;
        try {
            $hasPoHeaders = (bool) PoHeader::query()->limit(1)->exists();
        } catch (\Throwable $e) {
            $hasPoHeaders = false;
        }

        $purchaseOrdersTable = $this->resolveTableName('purchase_orders');
        $poCols = $this->columnMap($purchaseOrdersTable);
        $poDocCol = $poCols['po_doc'] ?? ($poCols['po_number'] ?? null);
        $purchaseOrdersHasRows = false;
        if ($purchaseOrdersTable && $poDocCol) {
            try {
                $purchaseOrdersHasRows = (bool) DB::table($purchaseOrdersTable)->limit(1)->exists();
            } catch (\Throwable $e) {
                $purchaseOrdersHasRows = false;
            }
        }

        $usePurchaseOrders = !$hasPoHeaders && $purchaseOrdersHasRows && $poDocCol;

        if ($usePurchaseOrders) {
            $quote = fn (string $name) => $this->quoteIdentifier($name);
            $poDateCol = $poCols['created_date'] ?? ($poCols['order_date'] ?? ($poCols['created_at'] ?? null));
            $poVendorNameCol = $poCols['vendor_name'] ?? null;
            $poLineCol = $poCols['line_no'] ?? null;
            $poItemDescCol = $poCols['item_desc'] ?? null;
            $poItemCodeCol = $poCols['item_code'] ?? ($poCols['model_code'] ?? null);
            $poQtyCol = $poCols['qty'] ?? ($poCols['quantity'] ?? ($poCols['qty_ordered'] ?? null));
            $poUomCol = $poCols['uom'] ?? null;

            $headersQuery = DB::table($purchaseOrdersTable)
                ->select(array_filter([
                    DB::raw($quote($poDocCol).' as po_number'),
                    $poDateCol ? DB::raw('MAX('.$quote($poDateCol).') as po_date') : DB::raw('NULL as po_date'),
                    $poVendorNameCol ? DB::raw('MAX('.$quote($poVendorNameCol).') as supplier') : DB::raw('NULL as supplier'),
                ]))
                ->whereNotNull($poDocCol)
                ->groupBy(DB::raw($quote($poDocCol)));

            if ($q !== '') {
                $headersQuery->where(function ($s) use ($q, $poDocCol, $poVendorNameCol) {
                    $hasCondition = false;
                    if ($poDocCol) {
                        $s->orWhere($poDocCol, 'like', "%{$q}%");
                        $hasCondition = true;
                    }
                    if ($poVendorNameCol) {
                        $s->orWhere($poVendorNameCol, 'like', "%{$q}%");
                        $hasCondition = true;
                    }
                    if (!$hasCondition) {
                        $s->whereRaw('1 = 0');
                    }
                });
            }

            if ($poDateCol) {
                $headersQuery->orderByDesc('po_date');
            }
            $headersQuery->orderBy('po_number');

            $headers = $headersQuery->paginate($perPage)->appends($request->query());

            $poNumbers = collect($headers->items())->pluck('po_number')->filter()->values()->all();

            $linesByPo = collect();
            if (!empty($poNumbers)) {
                $lines = DB::table($purchaseOrdersTable)
                    ->select(array_filter([
                        DB::raw($quote($poDocCol).' as po_number'),
                        $poLineCol ? DB::raw($quote($poLineCol).' as line_no') : DB::raw('NULL as line_no'),
                        $poItemDescCol ? DB::raw($quote($poItemDescCol).' as item_desc') : DB::raw('NULL as item_desc'),
                        $poItemCodeCol ? DB::raw($quote($poItemCodeCol).' as model_code') : DB::raw('NULL as model_code'),
                        $poUomCol ? DB::raw($quote($poUomCol).' as uom') : DB::raw('NULL as uom'),
                        $poQtyCol ? DB::raw('COALESCE('.$quote($poQtyCol).',0) as qty_ordered') : DB::raw('0 as qty_ordered'),
                    ]))
                    ->whereIn($poDocCol, $poNumbers)
                    ->orderBy($poDocCol)
                    ->when($poLineCol, fn ($q) => $q->orderBy($poLineCol))
                    ->get();

                $linesByPo = $lines->groupBy('po_number');
            }

            $shipByLine = [];
            $grByLine = [];

            if (!empty($poNumbers)) {
                $invoiceTable = $this->resolveTableName('invoices');
                $invoiceCols = $this->columnMap($invoiceTable);
                $invPoCol = $invoiceCols['po_no'] ?? null;
                $invLineCol = $invoiceCols['line_no'] ?? null;
                $invDateCol = $invoiceCols['invoice_date'] ?? null;
                $invQtyCol = $invoiceCols['qty'] ?? null;

                if ($invoiceTable && $invPoCol && $invLineCol && $invQtyCol) {
                    $invoices = DB::table($invoiceTable)
                        ->select(array_filter([
                            DB::raw($quote($invPoCol).' as po_no'),
                            DB::raw($quote($invLineCol).' as line_no'),
                            $invDateCol ? DB::raw($quote($invDateCol).' as invoice_date') : DB::raw('NULL as invoice_date'),
                            DB::raw($quote($invQtyCol).' as qty'),
                        ]))
                        ->whereIn($invPoCol, $poNumbers)
                        ->get();

                    foreach ($invoices as $row) {
                        $key = $row->po_no.'#'.(string) $row->line_no;
                        $shipByLine[$key][] = [
                            'date' => $row->invoice_date,
                            'type' => 'shipment',
                            'qty'  => (float) $row->qty,
                        ];
                    }
                }

                $grTable = $this->resolveTableName('gr_receipts');
                $grCols = $this->columnMap($grTable);
                $grPoCol = $grCols['po_no'] ?? null;
                $grLineCol = $grCols['line_no'] ?? null;
                $grDateCol = $grCols['receive_date'] ?? null;
                $grQtyCol = $grCols['qty'] ?? null;

                if ($grTable && $grPoCol && $grLineCol && $grQtyCol) {
                    $gr = DB::table($grTable)
                        ->select(array_filter([
                            DB::raw($quote($grPoCol).' as po_no'),
                            DB::raw($quote($grLineCol).' as line_no'),
                            $grDateCol ? DB::raw($quote($grDateCol).' as receive_date') : DB::raw('NULL as receive_date'),
                            DB::raw($quote($grQtyCol).' as qty'),
                        ]))
                        ->whereIn($grPoCol, $poNumbers)
                        ->get();

                    foreach ($gr as $row) {
                        $key = $row->po_no.'#'.(string) $row->line_no;
                        $grByLine[$key][] = [
                            'date' => $row->receive_date,
                            'type' => 'gr',
                            'qty'  => (float) $row->qty,
                        ];
                    }
                }
            }

            $poData = [];
            foreach ($headers as $header) {
                $poNo = $header->po_number;
                $summary = [
                    'ordered_total' => 0.0,
                    'shipped_total' => 0.0,
                    'received_total'=> 0.0,
                    'in_transit'    => 0.0,
                    'remaining'     => 0.0,
                ];

                $linesOut = [];
                $linesForPo = $linesByPo->get($poNo, collect());
                foreach ($linesForPo as $line) {
                    $ordered = (float) ($line->qty_ordered ?? 0);
                    $key = $poNo.'#'.(string) $line->line_no;

                    $events = array_merge($shipByLine[$key] ?? [], $grByLine[$key] ?? []);

                    usort($events, function ($a, $b) {
                        $da = $a['date'] ?? '';
                        $db = $b['date'] ?? '';
                        if ($da === $db) {
                            if ($a['type'] === $b['type']) { return 0; }
                            return $a['type'] === 'shipment' ? -1 : 1;
                        }
                        return strcmp((string) $da, (string) $db);
                    });

                    $shippedCum = 0.0;
                    $receivedCum = 0.0;
                    $computedRows = [];
                    foreach ($events as $ev) {
                        if ($ev['type'] === 'shipment') {
                            $shippedCum += (float) $ev['qty'];
                        } else {
                            $receivedCum += (float) $ev['qty'];
                        }
                        $inTransit = max($shippedCum - $receivedCum, 0.0);
                        $remaining = max($ordered - $receivedCum, 0.0);
                        $computedRows[] = [
                            'date' => $ev['date'],
                            'type' => $ev['type'],
                            'qty'  => (float) $ev['qty'],
                            'ship_sum' => $shippedCum,
                            'gr_sum'   => $receivedCum,
                            'in_transit' => $inTransit,
                            'remaining'  => $remaining,
                        ];
                    }

                    $shippedTotal = 0.0;
                    foreach ($shipByLine[$key] ?? [] as $e) { $shippedTotal += (float) $e['qty']; }
                    $receivedTotal = 0.0;
                    foreach ($grByLine[$key] ?? [] as $e) { $receivedTotal += (float) $e['qty']; }

                    $lineInTransit = max($shippedTotal - $receivedTotal, 0.0);
                    $lineRemaining = max($ordered - $receivedTotal, 0.0);

                    $linesOut[] = [
                        'line_no' => $line->line_no,
                        'item_desc' => $line->item_desc,
                        'model_code' => $line->model_code,
                        'uom' => $line->uom,
                        'ordered' => $ordered,
                        'shipped_total' => $shippedTotal,
                        'received_total' => $receivedTotal,
                        'in_transit' => $lineInTransit,
                        'remaining' => $lineRemaining,
                        'events' => $computedRows,
                    ];

                    $summary['ordered_total'] += $ordered;
                    $summary['shipped_total'] += $shippedTotal;
                    $summary['received_total'] += $receivedTotal;
                }

                $summary['in_transit'] = max($summary['shipped_total'] - $summary['received_total'], 0.0);
                $summary['remaining']  = max($summary['ordered_total'] - $summary['received_total'], 0.0);

                $poDate = null;
                if (!empty($header->po_date)) {
                    try {
                        $poDate = \Illuminate\Support\Carbon::parse($header->po_date)->toDateString();
                    } catch (\Throwable $e) {
                        $poDate = (string) $header->po_date;
                    }
                }

                $poData[$poNo] = [
                    'summary' => $summary,
                    'lines' => $linesOut,
                    'meta' => [
                        'po_date' => $poDate,
                        'supplier' => $header->supplier,
                        'currency' => $header->currency ?? null,
                    ],
                ];
            }

            return view('admin.po_progress.index', [
                'headers' => $headers,
                'poData' => $poData,
                'q' => $q,
                'perPage' => $perPage,
            ]);
        }

        $headersQuery = PoHeader::query()
            ->with(['lines' => function ($q) {
                $q->orderBy('line_no');
            }])
            ->orderByDesc('po_date')
            ->orderBy('po_number');

        if ($q !== '') {
            $headersQuery->where(function ($s) use ($q) {
                $s->where('po_number', 'like', "%{$q}%")
                  ->orWhere('supplier', 'like', "%{$q}%");
            });
        }

        $headers = $headersQuery->paginate($perPage)->appends($request->query());

        $poNumbers = collect($headers->items())->pluck('po_number')->filter()->values()->all();

        $poData = [];
        if (!empty($poNumbers)) {
            $invoices = DB::table('invoices')
                ->whereIn('po_no', $poNumbers)
                ->get(['po_no', 'line_no', 'invoice_date', 'qty']);

            $gr = DB::table('gr_receipts')
                ->whereIn('po_no', $poNumbers)
                ->get(['po_no', 'line_no', 'receive_date', 'qty']);

            $shipByLine = [];
            foreach ($invoices as $row) {
                $key = $row->po_no.'#'.(string)$row->line_no;
                $shipByLine[$key][] = [
                    'date' => $row->invoice_date,
                    'type' => 'shipment',
                    'qty'  => (float) $row->qty,
                ];
            }

            $grByLine = [];
            foreach ($gr as $row) {
                $key = $row->po_no.'#'.(string)$row->line_no;
                $grByLine[$key][] = [
                    'date' => $row->receive_date,
                    'type' => 'gr',
                    'qty'  => (float) $row->qty,
                ];
            }

            foreach ($headers as $header) {
                $poNo = $header->po_number;
                $summary = [
                    'ordered_total' => 0.0,
                    'shipped_total' => 0.0,
                    'received_total'=> 0.0,
                    'in_transit'    => 0.0,
                    'remaining'     => 0.0,
                ];

                $linesOut = [];
                foreach ($header->lines as $line) {
                    $ordered = (float) ($line->qty_ordered ?? 0);
                    $key = $poNo.'#'.(string)$line->line_no;

                    $events = array_merge($shipByLine[$key] ?? [], $grByLine[$key] ?? []);

                    usort($events, function ($a, $b) {
                        $da = $a['date'] ?? '';
                        $db = $b['date'] ?? '';
                        if ($da === $db) {
                            if ($a['type'] === $b['type']) { return 0; }
                            return $a['type'] === 'shipment' ? -1 : 1;
                        }
                        return strcmp((string)$da, (string)$db);
                    });

                    $shippedCum = 0.0;
                    $receivedCum = 0.0;
                    $computedRows = [];
                    foreach ($events as $ev) {
                        if ($ev['type'] === 'shipment') {
                            $shippedCum += (float) $ev['qty'];
                        } else { // gr
                            $receivedCum += (float) $ev['qty'];
                        }
                        $inTransit = max($shippedCum - $receivedCum, 0.0);
                        $remaining = max($ordered - $receivedCum, 0.0);
                        $computedRows[] = [
                            'date' => $ev['date'],
                            'type' => $ev['type'],
                            'qty'  => (float) $ev['qty'],
                            'ship_sum' => $shippedCum,
                            'gr_sum'   => $receivedCum,
                            'in_transit' => $inTransit,
                            'remaining'  => $remaining,
                        ];
                    }

                    $shippedTotal = 0.0;
                    foreach ($shipByLine[$key] ?? [] as $e) { $shippedTotal += (float) $e['qty']; }
                    $receivedTotal = 0.0;
                    foreach ($grByLine[$key] ?? [] as $e) { $receivedTotal += (float) $e['qty']; }

                    $lineInTransit = max($shippedTotal - $receivedTotal, 0.0);
                    $lineRemaining = max($ordered - $receivedTotal, 0.0);

                    $linesOut[] = [
                        'line_no' => $line->line_no,
                        'item_desc' => $line->item_desc,
                        'model_code' => $line->model_code,
                        'uom' => $line->uom,
                        'ordered' => $ordered,
                        'shipped_total' => $shippedTotal,
                        'received_total' => $receivedTotal,
                        'in_transit' => $lineInTransit,
                        'remaining' => $lineRemaining,
                        'events' => $computedRows,
                    ];

                    $summary['ordered_total'] += $ordered;
                    $summary['shipped_total'] += $shippedTotal;
                    $summary['received_total'] += $receivedTotal;
                }

                $summary['in_transit'] = max($summary['shipped_total'] - $summary['received_total'], 0.0);
                $summary['remaining']  = max($summary['ordered_total'] - $summary['received_total'], 0.0);

                $poData[$poNo] = [
                    'summary' => $summary,
                    'lines' => $linesOut,
                    'meta' => [
                        'po_date' => optional($header->po_date)->toDateString(),
                        'supplier' => $header->supplier,
                        'currency' => $header->currency,
                    ],
                ];
            }
        }

        return view('admin.po_progress.index', [
            'headers' => $headers,
            'poData' => $poData,
            'q' => $q,
            'perPage' => $perPage,
        ]);
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
        if (!$table) {
            return [];
        }

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

    private function quoteIdentifier(string $name): string
    {
        return DB::connection()->getDriverName() === 'sqlsrv'
            ? '['.$name.']'
            : '"'.$name.'"';
    }
}
