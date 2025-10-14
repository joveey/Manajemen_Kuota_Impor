<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductQuotaMapping;
use App\Models\Quota;

class ProductQuotaAutoMapper
{
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

