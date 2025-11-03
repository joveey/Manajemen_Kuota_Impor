<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class OpenPoReader
{
    /**
     * Read Excel/CSV and return normalized rows.
     * Supports legacy "List PO" format and the new "PO Listed" export.
     * Columns are mapped case-insensitively to canonical keys.
     *
     * @return array{rows:array<int,array<string,mixed>>,model_map:array<string,string>}
     */
    public function read(string $fullPath): array
    {
        $spreadsheet = IOFactory::load($fullPath);

        /** @var Worksheet|null $sheet */
        $sheet = $spreadsheet->getSheetByName('List PO');
        if (!$sheet) {
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                if (strcasecmp($ws->getTitle(), 'List PO') === 0) { $sheet = $ws; break; }
            }
        }
        // CSV does not have sheet name; fallback to first sheet
        if (!$sheet) {
            $sheet = $spreadsheet->getSheet(0);
        }

        $highestRow = (int) $sheet->getHighestRow();
        $highestColIndex = (int) Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // Build header map (row 1) with normalization + canonical mapping
        $headers = [];
        $norm = static function (string $label): string {
            $s = strtoupper(trim($label));
            $s = preg_replace('/[^A-Z0-9]+/i', '', $s) ?? '';
            return $s;
        };
        $mapCanonical = [
            // PO Listed headers
            'MONTH' => 'MONTH',
            'PURCHASINGDOCTYPE' => 'DOC_TYPE',
            'VENDORSUPPLYINGPLANT' => 'VENDOR_MIX',
            'PURCHASINGDOCUMENT' => 'PO_DOC',
            'MATERIAL' => 'ITEM_CODE',
            'PLANT' => 'PLANT_CODE',
            'STORAGELOCATION' => 'STORAGE_LOCATION',
            'ORDERQUANTITY' => 'QTY',
            'STILLTOBEINVOICEDQTY' => 'QTY_TO_INVOICE',
            'STILLTOBEDELIVEREDQTY' => 'QTY_TO_DELIVER',
            'DELIVERYDATE' => 'DELIV_DATE',
            'DOCUMENTDATE' => 'CREATED_DATE',
            'HEADERTEXT' => 'ITEM_DESC',
            // Legacy aliases
            'PODOC' => 'PO_DOC',
            'CREATEDDATE' => 'CREATED_DATE',
            'DELIVDATE' => 'DELIV_DATE',
            'LINENO' => 'LINE_NO',
            'ITEMCODE' => 'ITEM_CODE',
            'ITEMDESC' => 'ITEM_DESC',
            'QTY' => 'QTY',
            'CURRENCY' => 'CURRENCY',
            'HSCODE' => 'HS_CODE',
            'VENDORNO' => 'VENDOR_NO',
            'VENDORNAME' => 'VENDOR_NAME',
        ];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $raw = (string) $sheet->getCell([$col, 1])->getValue();
            $n = $norm($raw);
            if ($n === '') { continue; }
            $canon = $mapCanonical[$n] ?? null;
            $headers[$col] = $canon ?: $n;
        }

        $rows = [];
        $allow = array_flip([
            'MONTH','DOC_TYPE','VENDOR_MIX','VENDOR_NO','VENDOR_NAME',
            'PO_DOC','CREATED_DATE','DELIV_DATE','LINE_NO','ITEM_CODE','ITEM_DESC','QTY','CURRENCY','HS_CODE',
            'PLANT_CODE','STORAGE_LOCATION','QTY_TO_INVOICE','QTY_TO_DELIVER',
        ]);
        for ($row = 2; $row <= $highestRow; $row++) {
            $assoc = ['ROW' => $row];
            $allEmpty = true;
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $h = $headers[$col] ?? null;
                if (!$h) { continue; }
                $val = $sheet->getCell([$col, $row])->getFormattedValue();
                if ($val !== null && $val !== '') { $allEmpty = false; }
                $assoc[$h] = $val;
            }
            if ($allEmpty) { continue; }
            // Parse vendor mix (e.g., "000123 - Supplier Name") into number & name
            if (!empty($assoc['VENDOR_MIX'])) {
                $mix = (string) $assoc['VENDOR_MIX'];
                $parts = preg_split('/\s*-\s*/', $mix, 2);
                if ($parts && count($parts) === 2) {
                    $assoc['VENDOR_NO'] = trim($parts[0]);
                    $assoc['VENDOR_NAME'] = trim($parts[1]);
                } else {
                    $assoc['VENDOR_NAME'] = trim($mix);
                }
            }
            // Keep only the keys we care about (plus ROW)
            $filtered = ['ROW' => $assoc['ROW']];
            foreach ($assoc as $k => $v) {
                if (isset($allow[$k])) { $filtered[$k] = $v; }
            }
            $rows[] = $filtered;
        }

        // Do not read any extra mapping sheets; rely solely on master Model→HS.
        $map = [];
        return ['rows' => $rows, 'model_map' => $map];
    }
}
