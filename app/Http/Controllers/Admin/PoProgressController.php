<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PoProgressController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) ($request->integer('per_page') ?: 10);
        $perPage = max(5, min($perPage, 50));

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

        // Collect PO numbers for current page
        $poNumbers = collect($headers->items())->pluck('po_number')->filter()->values()->all();

        $poData = [];
        if (!empty($poNumbers)) {
            // Preload invoices (as Shipment forecast) and GR receipts for these POs
            $invoices = DB::table('invoices')
                ->whereIn('po_no', $poNumbers)
                ->get(['po_no', 'line_no', 'invoice_date', 'qty']);

            $gr = DB::table('gr_receipts')
                ->whereIn('po_no', $poNumbers)
                ->get(['po_no', 'line_no', 'receive_date', 'qty']);

            // Group them per (po_no, line_no)
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
                    'in_transit_shipping' => 0.0,
                    'in_transit_not_ship_yet' => 0.0,
                    'remaining'     => 0.0,
                ];

                $linesOut = [];
                foreach ($header->lines as $line) {
                    $ordered = (float) ($line->qty_ordered ?? 0);
                    $key = $poNo.'#'.(string)$line->line_no;

                    $events = array_merge($shipByLine[$key] ?? [], $grByLine[$key] ?? []);

                    // Sort events by date asc, Shipment before GR on same date
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
                    $v = strtolower(trim((string) ($line->voyage_status ?? '')));
                    $lineShipIT = ($v === 'shipping') ? $lineInTransit : 0.0;
                    $lineNotShipIT = ($v === 'shipping') ? 0.0 : $lineInTransit; // treat others/blank as Not Ship Yet
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
                        'in_transit_shipping' => $lineShipIT,
                        'in_transit_not_ship_yet' => $lineNotShipIT,
                        'remaining' => $lineRemaining,
                        'events' => $computedRows,
                    ];

                    // Update summary
                    $summary['ordered_total'] += $ordered;
                    $summary['shipped_total'] += $shippedTotal;
                    $summary['received_total'] += $receivedTotal;
                }

                // Derive totals with non-negative constraints
                $summary['in_transit'] = max($summary['shipped_total'] - $summary['received_total'], 0.0);
                // Aggregate split using per-line computed values
                foreach ($linesOut as $ln) {
                    $summary['in_transit_shipping'] += (float) ($ln['in_transit_shipping'] ?? 0);
                    $summary['in_transit_not_ship_yet'] += (float) ($ln['in_transit_not_ship_yet'] ?? 0);
                }
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
}
