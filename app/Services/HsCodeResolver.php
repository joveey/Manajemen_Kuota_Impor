<?php
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class HsCodeResolver
{
    /**
     * Resolve PK anchor for a product based on HS mapping.
     * If $period is provided (YYYY, YYYY-MM, or YYYY-MM-DD), prefer mapping for that year,
     * falling back to legacy rows (period_key = '').
     */
    public function resolveForProduct(Product $product, ?string $period = null): ?float
    {
        $hs = $product->hs_code ?? null;
        if (!empty($hs) && DB::getSchemaBuilder()->hasTable('hs_code_pk_mappings')) {
            $hasPeriodCol = DB::getSchemaBuilder()->hasColumn('hs_code_pk_mappings', 'period_key');
            $year = null;
            if (!empty($period)) {
                $p = trim((string)$period);
                if (preg_match('/^(\d{4})/', $p, $m)) {
                    $year = $m[1];
                }
            }

            if ($hasPeriodCol) {
                if ($year) {
                    $row = DB::table('hs_code_pk_mappings')
                        ->where('hs_code', $hs)
                        ->where(function ($q) use ($year) {
                            $q->where('period_key', $year)
                              ->orWhere('period_key', '');
                        })
                        ->orderByRaw("CASE WHEN period_key = ? THEN 0 WHEN period_key = '' THEN 1 ELSE 2 END", [$year])
                        ->first();
                } else {
                    $row = DB::table('hs_code_pk_mappings')
                        ->where('hs_code', $hs)
                        ->orderByRaw("CASE WHEN period_key = '' THEN 0 ELSE 1 END")
                        ->orderByDesc('period_key')
                        ->first();
                }
            } else {
                $row = DB::table('hs_code_pk_mappings')
                    ->where('hs_code', $hs)
                    ->first();
            }

            if ($row && isset($row->pk_capacity)) {
                return (float) $row->pk_capacity;
            }
        }

        if (isset($product->pk_capacity) && $product->pk_capacity !== null) {
            return (float) $product->pk_capacity;
        }
        return null;
    }

    /**
     * Resolve PK anchor directly from an HS code string for an optional period.
     * Returns null if not found.
     */
    public function resolvePkForHsCode(string $hs, ?string $period = null): ?float
    {
        $hs = trim($hs);
        if ($hs === '' || !DB::getSchemaBuilder()->hasTable('hs_code_pk_mappings')) {
            return null;
        }

        $hasPeriodCol = DB::getSchemaBuilder()->hasColumn('hs_code_pk_mappings', 'period_key');
        $year = null;
        if (!empty($period) && preg_match('/^(\d{4})/', (string)$period, $m)) { $year = $m[1]; }

        if ($hasPeriodCol) {
            if ($year) {
                $row = DB::table('hs_code_pk_mappings')
                    ->where('hs_code', $hs)
                    ->where(function ($q) use ($year) { $q->where('period_key', $year)->orWhere('period_key', ''); })
                    ->orderByRaw("CASE WHEN period_key = ? THEN 0 WHEN period_key = '' THEN 1 ELSE 2 END", [$year])
                    ->first();
            } else {
                $row = DB::table('hs_code_pk_mappings')
                    ->where('hs_code', $hs)
                    ->orderByRaw("CASE WHEN period_key = '' THEN 0 ELSE 1 END")
                    ->orderByDesc('period_key')
                    ->first();
            }
        } else {
            $row = DB::table('hs_code_pk_mappings')->where('hs_code', $hs)->first();
        }

        return ($row && isset($row->pk_capacity)) ? (float)$row->pk_capacity : null;
    }
}
