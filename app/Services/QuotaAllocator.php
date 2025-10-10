<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Quota;
use App\Services\Exceptions\InsufficientQuotaException;
use Illuminate\Support\Collection;

class QuotaAllocator
{
    public function allocate(Product $product, int $quantity): QuotaAllocationResult
    {
        /** @var Collection<int, \App\Models\ProductQuotaMapping> $mappings */
        $mappings = $product->quotaMappings()
            ->with(['quota' => function ($query) {
                $query->lockForUpdate();
            }])
            ->orderBy('priority')
            ->get();

        if ($mappings->isEmpty()) {
            // No explicit mappings: try to locate an active quota that matches the product
            $fallback = Quota::query()
                ->active()
                ->where('forecast_remaining', '>=', $quantity)
                ->orderByDesc('period_start')
                ->get()
                ->first(fn (Quota $q) => $q->matchesProduct($product));

            if ($fallback) {
                return new QuotaAllocationResult($fallback, null);
            }

            throw InsufficientQuotaException::forProduct($product->name, $quantity);
        }

        $initialQuota = optional($mappings->first())->quota;

        foreach ($mappings as $mapping) {
            $quota = $mapping->quota;

            if (!$quota || !$quota->is_active) {
                continue;
            }

            if ($quota->forecast_remaining >= $quantity) {
                return new QuotaAllocationResult($quota, $initialQuota);
            }
        }
        // Fallback: find a new active quota outside current mappings that matches and has enough forecast
        $mappedIds = $mappings->pluck('quota_id')->all();
        $fallback = Quota::query()
            ->active()
            ->whereNotIn('id', $mappedIds)
            ->where('forecast_remaining', '>=', $quantity)
            ->orderByDesc('period_start')
            ->get()
            ->first(fn (Quota $q) => $q->matchesProduct($product));

        if ($fallback) {
            // Optionally register a new mapping with lowest priority so future allocations see it
            $maxPriority = (int) $mappings->max('priority');
            $product->quotaMappings()->create([
                'quota_id' => $fallback->id,
                'priority' => $maxPriority + 1,
                'is_primary' => false,
                'notes' => 'Auto-mapped during allocation',
            ]);

            return new QuotaAllocationResult($fallback, $initialQuota);
        }

        throw InsufficientQuotaException::forProduct($product->name, $quantity);
    }
}
