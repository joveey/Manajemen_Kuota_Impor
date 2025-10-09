<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductQuotaMapping;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Models\QuotaHistory;
use App\Models\Shipment;
use App\Models\ShipmentReceipt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SampleQuotaDataSeeder extends Seeder
{
    public function run(): void
    {
        $products = collect([
            [
                'code' => 'PRD-001',
                'name' => 'AC CS-LN5WKJ 0.5 PK',
                'sap_model' => 'CS-LN5WKJ',
                'category' => 'Scheme XX1',
                'pk_capacity' => 0.5,
                'description' => 'Split wall inverter 0.5 PK',
            ],
            [
                'code' => 'PRD-002',
                'name' => 'AC CS-LN9WKJ 1 PK',
                'sap_model' => 'CS-LN9WKJ',
                'category' => 'Scheme XX1',
                'pk_capacity' => 1.0,
                'description' => 'Split wall inverter 1 PK',
            ],
            [
                'code' => 'PRD-003',
                'name' => 'AC CS-J18PKJ 2 PK',
                'sap_model' => 'CS-J18PKJ',
                'category' => 'Scheme XX1',
                'pk_capacity' => 2.0,
                'description' => 'Split wall 2 PK high efficiency',
            ],
            [
                'code' => 'PRD-004',
                'name' => 'AC CS-X24PKJ 2.5 PK',
                'sap_model' => 'CS-X24PKJ',
                'category' => 'Scheme YY1',
                'pk_capacity' => 2.5,
                'description' => 'Commercial split AC 2.5 PK',
            ],
            [
                'code' => 'PRD-005',
                'name' => 'AC CS-X34PKJ 3.5 PK',
                'sap_model' => 'CS-X34PKJ',
                'category' => 'Scheme YY1',
                'pk_capacity' => 3.5,
                'description' => 'Commercial split AC 3.5 PK heavy duty',
            ],
        ])->map(function (array $item) {
            return Product::updateOrCreate(
                ['code' => $item['code']],
                $item + ['is_active' => true]
            );
        });

        $quotas = collect([
            [
                'quota_number' => 'XX1',
                'name' => 'Kuota Pemerintah 0.5 PK - 2 PK',
                'government_category' => 'AC 0.5 PK - 2 PK',
                'period_start' => '2025-01-01',
                'period_end' => '2025-12-31',
                'total_allocation' => 100000,
                'forecast_remaining' => 85000,
                'actual_remaining' => 92000,
                'status' => Quota::STATUS_AVAILABLE,
                'source_document' => 'SK Menteri Perdagangan No. 123/2025',
            ],
            [
                'quota_number' => 'XX2',
                'name' => 'Kuota Cadangan 0.5 PK - 2 PK',
                'government_category' => 'AC 0.5 PK - 2 PK',
                'period_start' => '2025-07-01',
                'period_end' => '2025-12-31',
                'total_allocation' => 50000,
                'forecast_remaining' => 50000,
                'actual_remaining' => 50000,
                'status' => Quota::STATUS_AVAILABLE,
                'source_document' => 'SK Menteri Perdagangan No. 456/2025',
            ],
            [
                'quota_number' => 'YY1',
                'name' => 'Kuota Pemerintah 2 PK - 4 PK',
                'government_category' => 'AC 2 PK - 4 PK',
                'period_start' => '2025-01-01',
                'period_end' => '2025-12-31',
                'total_allocation' => 60000,
                'forecast_remaining' => 52000,
                'actual_remaining' => 58000,
                'status' => Quota::STATUS_AVAILABLE,
                'source_document' => 'SK Menteri Perdagangan No. 789/2025',
            ],
            [
                'quota_number' => 'YY2',
                'name' => 'Kuota Cadangan 2 PK - 4 PK',
                'government_category' => 'AC 2 PK - 4 PK',
                'period_start' => '2025-08-01',
                'period_end' => '2025-12-31',
                'total_allocation' => 30000,
                'forecast_remaining' => 30000,
                'actual_remaining' => 30000,
                'status' => Quota::STATUS_AVAILABLE,
                'source_document' => 'SK Menteri Perdagangan No. 790/2025',
            ],
        ])->map(function (array $item) {
            return Quota::updateOrCreate(
                ['quota_number' => $item['quota_number']],
                $item + [
                    'notes' => 'Sample data seeding',
                    'is_active' => true,
                ]
            );
        });

        // Mapping products ke kuota berdasarkan kapasitas PK dengan fallback prioritas
        foreach ($products as $product) {
            $matchingQuotas = $quotas->filter(fn (Quota $quota) => $quota->matchesProduct($product))->values();

            if ($matchingQuotas->isEmpty()) {
                continue;
            }

            foreach ($matchingQuotas as $index => $quota) {
                ProductQuotaMapping::updateOrCreate([
                    'product_id' => $product->id,
                    'quota_id' => $quota->id,
                ], [
                    'priority' => $index + 1,
                    'is_primary' => $index === 0,
                    'notes' => $index === 0
                        ? 'Kuota utama berdasarkan kategori pemerintah'
                        : 'Fallback kuota otomatis ketika kuota utama habis',
                ]);
            }
        }

        // Sample purchase orders replicating SAP list columns
        $orders = [
            [
                'sequence_number' => 1,
                'period' => '2025-10',
                'po_number' => 'PO100001',
                'sap_reference' => 'SAP-REF-0001',
                'product_code' => 'PRD-001',
                'quantity' => 5000,
                'order_date' => '2025-10-01',
                'pgi_branch' => 'PGI GREAT JAKARTA 1',
                'customer_name' => 'PT Sentral Elec',
                'pic_name' => 'Arief Kurniawan',
                'status_po_display' => 'Released',
                'truck' => 'COD LONG',
                'moq' => 'Scheme XX1',
                'category' => 'Standard',
                'plant_name' => 'Panasonic Corp. Osaka Plant',
                'plant_detail' => '1-1 Matsushita-cho, Moriguchi, Osaka',
                'remarks' => 'First batch October',
            ],
            [
                'sequence_number' => 2,
                'period' => '2025-10',
                'po_number' => 'PO100002',
                'sap_reference' => 'SAP-REF-0002',
                'product_code' => 'PRD-002',
                'quantity' => 3000,
                'order_date' => '2025-10-02',
                'pgi_branch' => 'PGI BANDUNG',
                'customer_name' => 'CV Sinar Agung',
                'pic_name' => 'Bagas Tejo',
                'status_po_display' => 'In Progress Marketing',
                'truck' => 'TRONTON JAWA',
                'moq' => 'Scheme XX1',
                'category' => 'Standard',
                'plant_name' => 'Panasonic Corp. Osaka Plant',
                'plant_detail' => '1-1 Matsushita-cho, Moriguchi, Osaka',
                'remarks' => 'Urgent delivery for Bandung branch',
            ],
            [
                'sequence_number' => 3,
                'period' => '2025-10',
                'po_number' => 'PO100003',
                'sap_reference' => 'SAP-REF-0003',
                'product_code' => 'PRD-003',
                'quantity' => 2500,
                'order_date' => '2025-10-03',
                'pgi_branch' => 'PGI MAKASSAR',
                'customer_name' => 'PT Ecommerce Lestari',
                'pic_name' => 'Haryadi',
                'status_po_display' => 'Released',
                'truck' => 'Container 40 HC',
                'moq' => 'Scheme XX1',
                'category' => 'Standard',
                'plant_name' => 'Panasonic Corp. Osaka Plant',
                'plant_detail' => '1-1 Matsushita-cho, Moriguchi, Osaka',
                'remarks' => 'Makassar online channel',
            ],
            [
                'sequence_number' => 4,
                'period' => '2025-10',
                'po_number' => 'PO200001',
                'sap_reference' => 'SAP-REF-0201',
                'product_code' => 'PRD-004',
                'quantity' => 4000,
                'order_date' => '2025-10-05',
                'pgi_branch' => 'PGI SURABAYA',
                'customer_name' => 'PT Graha Elektrik',
                'pic_name' => 'Rudi Hartono',
                'status_po_display' => 'Released',
                'truck' => 'Trailer Jawa',
                'moq' => 'Scheme YY1',
                'category' => 'Commercial',
                'plant_name' => 'Panasonic Air Quality Plant',
                'plant_detail' => '2-2-1 Oaza, Kadoma, Osaka',
                'remarks' => 'Proyek hotel Surabaya',
            ],
            [
                'sequence_number' => 5,
                'period' => '2025-10',
                'po_number' => 'PO200002',
                'sap_reference' => 'SAP-REF-0202',
                'product_code' => 'PRD-005',
                'quantity' => 3500,
                'order_date' => '2025-10-07',
                'pgi_branch' => 'PGI MEDAN',
                'customer_name' => 'PT Prima Konstruksi',
                'pic_name' => 'Siti Rahma',
                'status_po_display' => 'In Progress Logistics',
                'truck' => 'Kontainer 40 HC',
                'moq' => 'Scheme YY1',
                'category' => 'Commercial',
                'plant_name' => 'Panasonic Air Quality Plant',
                'plant_detail' => '2-2-1 Oaza, Kadoma, Osaka',
                'remarks' => 'Pengadaan Pabrik Medan',
            ],
        ];

        foreach ($orders as $order) {
            $product = $products->firstWhere('code', $order['product_code']);
            if (!$product) {
                continue;
            }

            $quota = $quotas->first(fn (Quota $quota) => $quota->matchesProduct($product));
            if (!$quota) {
                continue;
            }

            $po = PurchaseOrder::updateOrCreate(
                ['po_number' => $order['po_number']],
                [
                    'sequence_number' => $order['sequence_number'],
                    'period' => $order['period'],
                    'sap_reference' => $order['sap_reference'],
                    'product_id' => $product->id,
                    'quota_id' => $quota->id,
                    'quantity' => $order['quantity'],
                    'order_date' => $order['order_date'],
                    'pgi_branch' => $order['pgi_branch'],
                    'customer_name' => $order['customer_name'],
                    'pic_name' => $order['pic_name'],
                    'status' => PurchaseOrder::STATUS_IN_TRANSIT,
                    'status_po_display' => $order['status_po_display'],
                    'truck' => $order['truck'],
                    'moq' => $order['moq'],
                    'category' => $order['category'],
                    'plant_name' => $order['plant_name'],
                    'plant_detail' => $order['plant_detail'],
                    'remarks' => $order['remarks'],
                    'forecast_deducted_at' => now(),
                ]
            );

            // Create shipments per PO (simulate partial shipments)
            $shipmentQty = (int) round($po->quantity * 0.6);
            $shipment = Shipment::updateOrCreate(
                ['shipment_number' => 'SHIP-' . $po->po_number],
                [
                    'purchase_order_id' => $po->id,
                    'quantity_planned' => $shipmentQty,
                    'quantity_received' => 0,
                    'ship_date' => Carbon::parse($po->order_date)->addDays(7),
                    'eta_date' => Carbon::parse($po->order_date)->addDays(30),
                    'status' => Shipment::STATUS_IN_TRANSIT,
                    'detail' => 'Main batch shipment',
                ]
            );

            // Record a receipt for first PO to demonstrate actual deduction
            if ($po->sequence_number === 1) {
                $receiptQty = (int) round($shipmentQty * 0.8);
                ShipmentReceipt::updateOrCreate(
                    [
                        'shipment_id' => $shipment->id,
                        'document_number' => 'RCPT-' . $shipment->shipment_number,
                    ],
                    [
                        'receipt_date' => Carbon::parse($shipment->ship_date)->addDays(28),
                        'quantity_received' => $receiptQty,
                        'notes' => 'First receipt confirmation',
                    ]
                );

                $shipment->quantity_received = $receiptQty;
                $shipment->receipt_date = Carbon::parse($shipment->ship_date)->addDays(28);
                $shipment->status = Shipment::STATUS_PARTIAL;
                $shipment->save();

                $po->quantity_received = $receiptQty;
                $po->status = PurchaseOrder::STATUS_PARTIAL;
                $po->save();

                $quota->decrementActual($receiptQty, 'Sample receipt seeded', $shipment, Carbon::parse($shipment->receipt_date));
            }

            $quota->decrementForecast($po->quantity, 'Sample PO seeded', $po, Carbon::parse($po->order_date));
        }

        // Ensure quota status is refreshed after seeding
        $quotas->each->refresh();
    }
}
