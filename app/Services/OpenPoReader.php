<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class OpenPoReader
{
    /**
     * Read Excel and return normalized rows from sheet "List PO".
     * Each row is an associative array with UPPER_SNAKE_CASE headers.
     *
     * @return array<int,array<string,mixed>> [ ['ROW' => 2, 'PO_NUMBER' => '...', ...], ... ]
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
        if (!$sheet) {
            throw new \RuntimeException('Sheet "List PO" not found');
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
            $rows[] = $assoc;
        }

        // Optional: read mapping sheet "mapping hs code by model"
        $map = [];
        $mapSheet = $spreadsheet->getSheetByName('mapping hs code by model');
        if (!$mapSheet) {
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                if (strcasecmp($ws->getTitle(), 'mapping hs code by model') === 0) { $mapSheet = $ws; break; }
            }
        }
        if ($mapSheet) {
            $maxRow = (int) $mapSheet->getHighestRow();
            $maxCol = (int) Coordinate::columnIndexFromString($mapSheet->getHighestColumn());
            $hdrs = [];
            for ($c=1;$c<=$maxCol;$c++) {
                $val = (string) $mapSheet->getCell([$c,1])->getValue();
                $key = strtoupper(trim(preg_replace('/\s+/', '_', str_replace(['-',' '], '_', $val))));
                if ($key !== '') { $hdrs[$c] = $key; }
            }
            $colModel = null; $colHs = null;
            foreach ($hdrs as $c=>$h) {
                if ($h === 'MODEL') { $colModel = $c; }
                if ($h === 'HS_CODE') { $colHs = $c; }
            }
            if ($colModel && $colHs) {
                for ($r=2;$r<=$maxRow;$r++) {
                    $m = trim((string)$mapSheet->getCell([$colModel,$r])->getFormattedValue());
                    $h = trim((string)$mapSheet->getCell([$colHs,$r])->getFormattedValue());
                    if ($m === '' || $h === '') { continue; }
                    $map[strtoupper($m)] = $h;
                }
            }
        }

        return ['rows' => $rows, 'model_map' => $map];
    }
}
