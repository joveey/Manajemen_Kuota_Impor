<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ModelHsImportService
{
    private const ERROR_LIMIT = 20;

    private array $modelHeaders = ['MODEL', 'MODEL_CODE', 'MODEL CODE', 'MATERIAL', 'SAP_MODEL', 'SAP MODEL', 'CODE'];
    private array $hsHeaders = ['HS', 'HS_CODE', 'HS CODE', 'HSCODE'];
    private array $periodHeaders = ['PERIOD', 'PERIOD_KEY', 'YEAR', 'YEAR_MONTH', 'YEAR-MONTH'];

    public function import(UploadedFile $file): array
    {
        if (!class_exists(IOFactory::class)) {
            throw new \RuntimeException('Missing phpoffice/phpspreadsheet dependency.');
        }

        if (!Schema::hasTable('hs_code_pk_mappings')) {
            throw new \RuntimeException('Table hs_code_pk_mappings not found.');
        }

        $hasPeriodCol = Schema::hasColumn('hs_code_pk_mappings', 'period_key');

        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $spreadsheet = $reader->load($file->getRealPath());

        $sheet = $this->locateSheet($spreadsheet);
        [$map, $headerRow] = $this->detectColumns($sheet);
        if (!$map['model'] || !$map['hs']) {
            throw new \RuntimeException('Kolom MODEL dan HS wajib ada di file.');
        }

        $summary = [
            'total_rows' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $rows = [];
        $highestRow = (int) $sheet->getHighestRow();
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $model = trim((string) $sheet->getCell([$map['model'], $row])->getValue());
            $hs = trim((string) $sheet->getCell([$map['hs'], $row])->getValue());
            $periodValue = $map['period'] ? trim((string) $sheet->getCell([$map['period'], $row])->getValue()) : '';

            if ($model === '' && $hs === '' && $periodValue === '') {
                continue;
            }

            $summary['total_rows']++;

            $errors = [];
            if ($model === '') {
                $errors[] = 'Model Code wajib';
            }
            if ($hs === '') {
                $errors[] = 'HS Code wajib';
            }

            $period = '';
            if ($hasPeriodCol && $periodValue !== '') {
                $period = $this->normalizePeriod($periodValue);
                if ($period === null) {
                    $errors[] = 'Period tidak valid (gunakan YYYY atau YYYY-MM)';
                }
            }

            if (!empty($errors)) {
                $summary['skipped']++;
                $this->pushError($summary['errors'], $row, implode('; ', $errors));
                continue;
            }

            $rows[] = [
                'row' => $row,
                'model_code' => strtoupper($model),
                'hs_code' => $this->normalizeHs($hs),
                'period_key' => $period ?? '',
            ];
        }

        if (empty($rows)) {
            return $summary;
        }

        $keys = $hasPeriodCol ? ['model_code', 'period_key'] : ['model_code'];
        $timestamp = now();

        DB::transaction(function () use (&$summary, $rows, $timestamp, $keys, $hasPeriodCol) {
            foreach ($rows as $data) {
                $payload = [
                    'model_code' => $data['model_code'],
                    'hs_code' => $data['hs_code'],
                    'updated_at' => $timestamp,
                ];
                if ($hasPeriodCol) {
                    $payload['period_key'] = $data['period_key'];
                }

                $existing = DB::table('hs_code_pk_mappings')
                    ->where('model_code', $data['model_code'])
                    ->when($hasPeriodCol, fn ($q) => $q->where('period_key', $data['period_key']))
                    ->first();

                if ($existing) {
                    DB::table('hs_code_pk_mappings')
                        ->where('id', $existing->id)
                        ->update(['hs_code' => $payload['hs_code'], 'updated_at' => $timestamp]);
                    $summary['updated']++;
                } else {
                    $payload['created_at'] = $timestamp;
                    DB::table('hs_code_pk_mappings')->insert($payload);
                    $summary['inserted']++;
                }
            }
        });

        return $summary;
    }

    private function locateSheet($spreadsheet)
    {
        $preferred = ['MODEL_HS', 'MODEL HS', 'HS', 'MODEL', 'MODEL->HS'];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = strtoupper(preg_replace('/\s+/', '', $sheet->getTitle()));
            foreach ($preferred as $name) {
                if ($title === strtoupper(preg_replace('/\s+/', '', $name))) {
                    return $sheet;
                }
            }
        }
        return $spreadsheet->getSheet(0);
    }

    private function detectColumns($sheet): array
    {
        $highestRow = min(20, (int) $sheet->getHighestRow());
        $highestCol = (int) \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $map = $this->mapHeaders($sheet, $row, $highestCol);
            if ($map['model'] && $map['hs']) {
                return [$map, $row];
            }
        }

        throw new \RuntimeException('Tidak menemukan header MODEL dan HS pada baris 1..20');
    }

    private function mapHeaders($sheet, int $row, int $highestCol): array
    {
        $normalize = fn ($value) => strtoupper(str_replace([' ', '_'], '', (string) $value));

        $map = [
            'model' => null,
            'hs' => null,
            'period' => null,
        ];

        for ($col = 1; $col <= $highestCol; $col++) {
            $value = $normalize($sheet->getCell([$col, $row])->getValue());
            if ($value === '') {
                continue;
            }

            if (in_array($value, array_map($normalize, $this->modelHeaders), true)) {
                $map['model'] = $col;
            } elseif (in_array($value, array_map($normalize, $this->hsHeaders), true)) {
                $map['hs'] = $col;
            } elseif (in_array($value, array_map($normalize, $this->periodHeaders), true)) {
                $map['period'] = $col;
            }
        }

        return $map;
    }

    private function normalizePeriod(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}[-\/](\d{2})$/', $value, $m)) {
            return substr($value, 0, 4);
        }
        return null;
    }

    private function normalizeHs(string $value): string
    {
        return strtoupper(str_replace([' ', '-', '.'], ['', '', '.'], $value));
    }

    private function pushError(array &$errors, int $row, string $message): void
    {
        if (count($errors) >= self::ERROR_LIMIT) {
            return;
        }
        $errors[] = ['row' => $row, 'message' => $message];
    }
}
