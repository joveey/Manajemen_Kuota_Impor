<?php

namespace App\Console\Commands;

use App\Services\ProductQuotaAutoMapper;
use App\Support\PkCategoryParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HsImportCommand extends Command
{
    protected $signature = 'hs:import {path} {--period=}';

    protected $description = 'Import HS code → PK anchor from Excel sheet "HS code master" (columns: HS_CODE, DESC). Optionally run automapper for a given period.';

    public function handle(): int
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $this->error('Missing dependency: phpoffice/phpspreadsheet');
            $this->line('Install it with: composer require phpoffice/phpspreadsheet');
            return self::FAILURE;
        }

        $path = (string) $this->argument('path');
        if (!is_file($path)) {
            $alt = base_path($path);
            if (is_file($alt)) {
                $path = $alt;
            } else {
                $this->error("File not found: {$path}");
                return self::FAILURE;
            }
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $this->error('Failed to load workbook: '.$e->getMessage());
            return self::FAILURE;
        }

        /** @var Worksheet|null $sheet */
        $sheet = $spreadsheet->getSheetByName('HS code master');
        if (!$sheet) {
            // Case-insensitive fallback
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                if (strcasecmp($ws->getTitle(), 'HS code master') === 0) {
                    $sheet = $ws;
                    break;
                }
            }
        }
        if (!$sheet) {
            $this->error('Sheet "HS code master" not found.');
            return self::FAILURE;
        }

        $highestRow = (int) $sheet->getHighestRow();
        $highestColIndex = (int) Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // Map headers (row 1)
        $headers = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = (string) $sheet->getCellByColumnAndRow($col, 1)->getValue();
            $key = strtoupper(trim($val));
            if ($key !== '') {
                $headers[$key] = $col;
            }
        }

        if (!isset($headers['HS_CODE']) || !isset($headers['DESC'])) {
            $this->error('Required columns not found: HS_CODE, DESC');
            return self::FAILURE;
        }

        $colHs = $headers['HS_CODE'];
        $colDesc = $headers['DESC'];

        $inserted = 0; $updated = 0; $skipped = 0;

        DB::beginTransaction();
        try {
            for ($row = 2; $row <= $highestRow; $row++) {
                $hsRaw = $sheet->getCellByColumnAndRow($colHs, $row)->getValue();
                $descRaw = $sheet->getCellByColumnAndRow($colDesc, $row)->getValue();

                $hs = trim((string) $hsRaw);
                $desc = is_null($descRaw) ? '' : (string) $descRaw;

                if ($hs === '') {
                    // ignore empty
                    continue;
                }

                $parsed = PkCategoryParser::parse($desc);
                $min = $parsed['min_pk'];
                $max = $parsed['max_pk'];

                // Determine anchor
                $anchor = null;
                if ($min === null && $max !== null) {
                    $anchor = (float) $max - 0.01;
                } elseif ($min !== null && $max !== null) {
                    $anchor = ((float) $min + (float) $max) / 2.0;
                } elseif ($min !== null && $max === null) {
                    $anchor = (float) $min + 0.01;
                }

                if ($anchor === null || !is_finite($anchor)) {
                    $skipped++;
                    $this->warn("Skipped row {$row}: unable to parse DESC='{$desc}'");
                    continue;
                }

                $anchor = round($anchor, 2);

                $existing = DB::table('hs_code_pk_mappings')->where('hs_code', $hs)->first();
                if ($existing) {
                    $current = isset($existing->pk_capacity) ? (float) $existing->pk_capacity : null;
                    if ($current === null || abs($current - $anchor) > 0.0001) {
                        DB::table('hs_code_pk_mappings')->where('id', $existing->id)->update([
                            'pk_capacity' => $anchor,
                            'updated_at' => now(),
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    DB::table('hs_code_pk_mappings')->insert([
                        'hs_code' => $hs,
                        'pk_capacity' => $anchor,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $inserted++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Import summary: inserted=%d updated=%d skipped=%d', $inserted, $updated, $skipped));

        $period = $this->option('period');
        if ($period !== null && $period !== false && $period !== '') {
            $summary = app(ProductQuotaAutoMapper::class)->runForPeriod($period);
            $this->info('AutoMapping: '.json_encode($summary));
        }

        return self::SUCCESS;
    }
}
