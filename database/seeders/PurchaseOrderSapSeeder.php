<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PurchaseOrderSapSeeder extends Seeder
{
    public function run(): void
    {
        $quota = Quota::first();
        if (! $quota) {
            $quota = Quota::create([
                'quota_number' => 'Q-2025-001',
                'name' => 'Default Quota',
                'government_category' => '1 PK - 2 PK',
                'total_allocation' => 10000,
                'forecast_remaining' => 8000,
                'actual_remaining' => 8000,
                'status' => Quota::STATUS_AVAILABLE,
                'is_active' => true,
            ]);
        }

        $productCatalog = [
            'U-12MS3H7' => 'FSV-EX MS3 D0 (MS3) 12.0HP',
            'S-22MEK2EA' => 'FSV High Static Ducted ID (ME3)',
            'S-28MEK2EA' => 'FSV High Static Ducted ID (MK2)',
        ];

        $products = [];
        foreach ($productCatalog as $code => $name) {
            $products[$code] = Product::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'sap_model' => $code,
                    'category' => 'AC',
                    'pk_capacity' => null,
                    'is_active' => true,
                ]
            );
        }

        $purchaseOrders = [
            [
                'po_number' => '7.971E+09',
                'created_date' => '2025-07-22',
                'vendor_number' => '21932',
                'vendor_name' => 'PANASONIC CORPORATION HEATING ANDCOOLING SOLUTIONS BUSINESS DIVISION',
                'line_number' => '10',
                'item_code' => 'U-12MS3H7',
                'item_description' => 'FSV-EX MS3 D0 (MS3) 12.0HP',
                'warehouse_code' => '7971',
                'warehouse_name' => 'PGI Head Office',
                'warehouse_source' => null,
                'subinventory_code' => 'M001',
                'subinventory_name' => 'E:A Class',
                'subinventory_source' => null,
                'quantity' => 3,
                'amount' => 645986,
                'category_code' => 'I01',
                'category' => 'Trade Overseas',
                'material_group' => 'COMAC',
                'sap_order_status' => 'Released',
            ],
            [
                'po_number' => '7.971E+10',
                'created_date' => '2025-07-30',
                'vendor_number' => '21932',
                'vendor_name' => 'PANASONIC CORPORATION HEATING ANDCOOLING SOLUTIONS BUSINESS DIVISION',
                'line_number' => '40',
                'item_code' => 'S-22MEK2EA',
                'item_description' => 'FSV High Static Ducted ID (ME3)',
                'warehouse_code' => '7971',
                'warehouse_name' => 'PGI Head Office',
                'warehouse_source' => null,
                'subinventory_code' => 'M001',
                'subinventory_name' => 'E:A Class',
                'subinventory_source' => null,
                'quantity' => 12,
                'amount' => 1120152,
                'category_code' => 'I01',
                'category' => 'Trade Overseas',
                'material_group' => 'COMAC',
                'sap_order_status' => 'In Transit',
            ],
            [
                'po_number' => '7.971E+11',
                'created_date' => '2025-08-05',
                'vendor_number' => '21932',
                'vendor_name' => 'PANASONIC CORPORATION HEATING ANDCOOLING SOLUTIONS BUSINESS DIVISION',
                'line_number' => '20',
                'item_code' => 'S-28MEK2EA',
                'item_description' => 'FSV High Static Ducted ID (MK2)',
                'warehouse_code' => '7971',
                'warehouse_name' => 'PGI Head Office',
                'warehouse_source' => null,
                'subinventory_code' => 'M001',
                'subinventory_name' => 'E:A Class',
                'subinventory_source' => null,
                'quantity' => 6,
                'amount' => 733050,
                'category_code' => 'I01',
                'category' => 'Trade Overseas',
                'material_group' => 'COMAC',
                'sap_order_status' => 'Delivered',
            ],
        ];

        $sequence = (int) PurchaseOrder::max('sequence_number');

        foreach ($purchaseOrders as $row) {
            $orderDate = Carbon::parse($row['created_date']);
            $sequence++;

            $data = [
                'sequence_number' => $sequence,
                'period' => $orderDate->format('Y-m'),
                'order_date' => $orderDate->toDateString(),
                'product_id' => $products[$row['item_code']]->id,
                'quota_id' => $quota->id,
                'quantity' => $row['quantity'],
                'amount' => $row['amount'],
                'vendor_number' => $row['vendor_number'],
                'vendor_name' => $row['vendor_name'],
                'line_number' => $row['line_number'],
                'item_code' => $row['item_code'],
                'item_description' => $row['item_description'],
                'warehouse_code' => $row['warehouse_code'],
                'warehouse_name' => $row['warehouse_name'],
                'warehouse_source' => $row['warehouse_source'],
                'subinventory_code' => $row['subinventory_code'],
                'subinventory_name' => $row['subinventory_name'],
                'subinventory_source' => $row['subinventory_source'],
                'category_code' => $row['category_code'],
                'category' => $row['category'],
                'material_group' => $row['material_group'],
                'sap_order_status' => $row['sap_order_status'],
                'status' => PurchaseOrder::STATUS_ORDERED,
                'status_po_display' => $row['sap_order_status'],
                'quantity_shipped' => 0,
                'quantity_received' => 0,
                'plant_name' => $row['warehouse_name'],
                'plant_detail' => 'Imported from SAP',
            ];

            PurchaseOrder::updateOrCreate(
                ['po_number' => $row['po_number']],
                $data
            );
        }
    }
}
