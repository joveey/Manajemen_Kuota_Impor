<?php
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class HsCodeResolver
{
    public function resolveForProduct(Product $product): ?float
    {
        // 1) coba dari tabel referensi hs_code_pk_mappings
        $hs = $product->hs_code ?? null; // Kolom ini mungkin tidak ada di proyek; fallback ke pk_capacity.
        if (!empty($hs)) {
            $row = DB::table('hs_code_pk_mappings')->where('hs_code', $hs)->first();
            if ($row && isset($row->pk_capacity)) {
                return (float) $row->pk_capacity;
            }
        }
        // 2) fallback: kolom pk_capacity di product jika ada
        if (isset($product->pk_capacity) && $product->pk_capacity !== null) {
            return (float) $product->pk_capacity;
        }
        return null;
    }
}

