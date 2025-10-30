<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class OpenPoReader
{
    /**
     * Read Excel/CSV and return normalized rows from sheet "List PO" (or first sheet for CSV).
     * Each row is an associative array with UPPER_SNAKE_CASE headers.
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

        // Build header map (row 1)
        $headers = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = (string) $sheet->getCell([$col, 1])->getValue();
            $key = strtoupper(trim(preg_replace('/\s+/', '_', str_replace(['-', ' '], '_', $val))));
            if ($key !== '') { $headers[$col] = $key; }
        }

        $rows = [];
        $allow = [
            'PO_DOC' => true,
            'CREATED_DATE' => true,
            'DELIV_DATE' => true,
            'LINE_NO' => true,
            'ITEM_CODE' => true,
            'ITEM_DESC' => true,
            'QTY' => true,
            'CURRENCY' => true,
            // keep HS_CODE if provided explicitly in PO (optional override)
            'HS_CODE' => true,
        ];
        for ($row = 2; $row <= $highestRow; $row++) {
            $assoc = ['ROW' => $row];
            $allEmpty = true;
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $h = $headers[$col] ?? null;
                if (!$h || !isset($allow[$h])) { continue; }
                $val = $sheet->getCell([$col, $row])->getFormattedValue();
                if ($val !== null && $val !== '') { $allEmpty = false; }
                $assoc[$h] = $val;
            }
            if ($allEmpty) { continue; }
            $rows[] = $assoc;
        }

        // Do not read any extra mapping sheets; rely solely on master Model→HS.
        $map = [];
        return ['rows' => $rows, 'model_map' => $map];
    }
}
