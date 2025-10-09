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

        throw InsufficientQuotaException::forProduct($product->name, $quantity);
    }
}
