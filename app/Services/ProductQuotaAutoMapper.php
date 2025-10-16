<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductQuotaMapping;
use App\Models\Quota;
use App\Support\PkCategoryParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class ProductQuotaAutoMapper
{
    /**
     * Jalankan automapping untuk satu periode (idempotent per-periode).
     *
     * - periodKey bisa: YYYY, YYYY-MM, atau YYYY-MM-DD.
     * - Menghapus mapping source='auto' untuk period_key terkait sebelum insert ulang.
     * - Insert primary (priority=1, is_primary=true) + backups (priority 2+), menandai source='auto' dan period_key.
     */
    public function runForPeriod(string|int $periodKey): array
    {
        $summary = [
            'mapped' => 0,
            'unmapped' => 0,
            'total_products' => 0,
        ];

        DB::transaction(function () use ($periodKey, &$summary) {
            // 1) Normalisasi quotas di periode tersebut
            $quotaQuery = $this->queryQuotasByPeriodKey($periodKey);

            $quotaQuery->chunkById(500, function ($quotas) use (&$summary) {
                foreach ($quotas as $q) {
                    $parsed = PkCategoryParser::parse((string)($q->government_category ?? ''));
                    $newMin = $parsed['min_pk'];
                    $newMax = $parsed['max_pk'];
                    $newMinIncl = (bool)$parsed['min_incl'];
                    $newMaxIncl = (bool)$parsed['max_incl'];

                    $changed = false;
                    if ((string)$q->min_pk !== (string)$newMin) { $q->min_pk = $newMin; $changed = true; }
                    if ((string)$q->max_pk !== (string)$newMax) { $q->max_pk = $newMax; $changed = true; }
                    if ((int)$q->is_min_inclusive !== (int)$newMinIncl) { $q->is_min_inclusive = $newMinIncl; $changed = true; }
                    if ((int)$q->is_max_inclusive !== (int)$newMaxIncl) { $q->is_max_inclusive = $newMaxIncl; $changed = true; }
                    if ($changed) {
                        $q->save();
                    }
                }
            });

            // Ambil ulang quotas kandidat (sudah ternormalisasi)
            $quotasForPeriod = $this->queryQuotasByPeriodKey($periodKey)->get();

            // 2) Ambil semua product sebagai master data
            $products = Product::query()->get();
            $summary['total_products'] = $products->count();

            $resolver = app(HsCodeResolver::class);

            // Bersihkan mapping otomatis periode ini terlebih dahulu (idempotent)
            DB::table('product_quota_mappings')
                ->where('period_key', (string)$periodKey)
                ->where('source', 'auto')
                ->delete();

            foreach ($products as $product) {
                $pk = $resolver->resolveForProduct($product);
                if ($pk === null) {
                    $summary['unmapped']++;
                    continue;
                }

                // 3) Filter quotas yang mengandung pk dalam rentang
                $candidates = $quotasForPeriod->filter(function (Quota $q) use ($pk) {
                    $minOk = true;
                    if (!is_null($q->min_pk)) {
                        $minOk = $q->is_min_inclusive ? ($pk >= (float)$q->min_pk) : ($pk > (float)$q->min_pk);
                    }
                    $maxOk = true;
                    if (!is_null($q->max_pk)) {
                        $maxOk = $q->is_max_inclusive ? ($pk <= (float)$q->max_pk) : ($pk < (float)$q->max_pk);
                    }
                    return $minOk && $maxOk;
                })->values();

                if ($candidates->isEmpty()) {
                    $summary['unmapped']++;
                    continue;
                }

                // Hitung lebar rentang (null => INF agar kalah prioritas)
                $computeWidth = function (Quota $q) {
                    $min = is_null($q->min_pk) ? null : (float)$q->min_pk;
                    $max = is_null($q->max_pk) ? null : (float)$q->max_pk;
                    if ($min === null || $max === null) {
                        return INF;
                    }
                    return max(0.0, $max - $min);
                };

                $sorted = $candidates->sortBy($computeWidth)->values();
                $primary = $sorted->first();
                $backups = $sorted->slice(1)->values();

                // 4) Insert mappings (auto) untuk periode ini
                $toInsert = [];
                $priority = 1;

                if ($primary) {
                    $toInsert[] = [
                        'product_id' => $product->id,
                        'quota_id' => $primary->id,
                        'priority' => $priority,
                        'is_primary' => true,
                        'source' => 'auto',
                        'period_key' => (string)$periodKey,
                        'notes' => 'auto',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $priority++;
                }

                foreach ($backups as $q) {
                    $toInsert[] = [
                        'product_id' => $product->id,
                        'quota_id' => $q->id,
                        'priority' => $priority,
                        'is_primary' => false,
                        'source' => 'auto',
                        'period_key' => (string)$periodKey,
                        'notes' => 'auto',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $priority++;
                }

                if (!empty($toInsert)) {
                    DB::table('product_quota_mappings')->upsert(
                        $toInsert,
                        ['product_id', 'quota_id', 'period_key'],
                        ['priority', 'is_primary', 'source', 'period_key', 'notes', 'updated_at']
                    );
                    $summary['mapped']++;
                }
            }

            Log::info(sprintf(
                'AutoMapping period=%s mapped=%d unmapped=%d total=%d',
                (string)$periodKey,
                $summary['mapped'],
                $summary['unmapped'],
                $summary['total_products']
            ));
        });

        return $summary;
    }

    private function queryQuotasByPeriodKey(string|int $periodKey)
    {
        $query = Quota::query();

        $key = (string)$periodKey;
        $key = trim($key);

        if (preg_match('/^\d{4}$/', $key)) {
            $year = (int)$key;
            $start = Carbon::create($year, 1, 1);
            $end = Carbon::create($year, 12, 31);
            $query->where(function ($q) use ($start, $end) {
                $q->where(function ($qq) use ($start) { $qq->whereNull('period_end')->orWhere('period_end', '>=', $start->toDateString()); })
                  ->where(function ($qq) use ($end)   { $qq->whereNull('period_start')->orWhere('period_start', '<=', $end->toDateString()); });
            });
        } elseif (preg_match('/^\d{4}-\d{2}$/', $key)) {
            [$y, $m] = explode('-', $key);
            $date = Carbon::create((int)$y, (int)$m, 1)->toDateString();
            $query->where(function ($q) use ($date) {
                $q->where(function ($qq) use ($date) { $qq->whereNull('period_start')->orWhere('period_start', '<=', $date); })
                  ->where(function ($qq) use ($date) { $qq->whereNull('period_end')->orWhere('period_end', '>=', $date); });
            });
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
            $date = $key;
            $query->where(function ($q) use ($date) {
                $q->where(function ($qq) use ($date) { $qq->whereNull('period_start')->orWhere('period_start', '<=', $date); })
                  ->where(function ($qq) use ($date) { $qq->whereNull('period_end')->orWhere('period_end', '>=', $date); });
            });
        }

        return $query;
    }

    public function sync(Product $product): void
    {
        if (!$product->is_active) {
            return;
        }

        $matchingQuotas = Quota::query()
            ->active()
            ->get()
            ->filter(fn (Quota $quota) => $quota->matchesProduct($product));

        if ($matchingQuotas->isEmpty()) {
            return;
        }

        $existingMappings = $product->quotaMappings()->pluck('id', 'quota_id');
        $nextPriority = (int) $product->quotaMappings()->max('priority') + 1;

        foreach ($matchingQuotas as $quota) {
            $mapping = ProductQuotaMapping::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'quota_id' => $quota->id,
                ],
                [
                    'priority' => $nextPriority,
                    'is_primary' => false,
                ]
            );

            if (!$existingMappings->has($quota->id)) {
                $nextPriority++;
            }
        }

        $this->ensurePrimary($product);
    }

    private function ensurePrimary(Product $product): void
    {
        $hasPrimary = $product->quotaMappings()->where('is_primary', true)->exists();
        if ($hasPrimary) {
            return;
        }

        $firstMapping = $product->quotaMappings()->orderBy('priority')->first();
        if ($firstMapping) {
            $firstMapping->is_primary = true;
            $firstMapping->save();
        }
    }
}
