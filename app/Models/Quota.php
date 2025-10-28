<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Product;

class Quota extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_LIMITED = 'limited';
    public const STATUS_DEPLETED = 'depleted';

    protected $fillable = [
        'quota_number',
        'name',
        'government_category',
        'period_start',
        'period_end',
        'total_allocation',
        'forecast_remaining',
        'actual_remaining',
        'status',
        'is_active',
        'source_document',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'is_active' => 'bool',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function quotaMappings()
    {
        return $this->hasMany(ProductQuotaMapping::class)
            ->orderBy('priority');
    }

    

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_quota_mappings')
            ->withPivot(['priority', 'is_primary', 'notes'])
            ->orderByPivot('priority');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function histories()
    {
        return $this->hasMany(QuotaHistory::class);
    }

    public function decrementForecast(int $quantity, ?string $description = null, ?Model $reference = null, ?\DateTimeInterface $occurredOn = null, ?int $userId = null): void
    {
        $this->forecast_remaining = max(0, $this->forecast_remaining - $quantity);
        $this->updateStatus();
        $this->save();

        $this->histories()->create([
            'change_type' => QuotaHistory::TYPE_FORECAST_DECREASE,
            'quantity_change' => -1 * $quantity,
            'occurred_on' => $occurredOn?->format('Y-m-d') ?? now()->toDateString(),
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $userId,
        ]);
    }

    public function decrementActual(int $quantity, ?string $description = null, ?Model $reference = null, ?\DateTimeInterface $occurredOn = null, ?int $userId = null, ?array $meta = null): void
    {
        $this->actual_remaining = max(0, $this->actual_remaining - $quantity);
        $this->updateStatus();
        $this->save();

        $this->histories()->create([
            'change_type' => QuotaHistory::TYPE_ACTUAL_DECREASE,
            'quantity_change' => -1 * $quantity,
            'occurred_on' => $occurredOn?->format('Y-m-d') ?? now()->toDateString(),
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $userId,
            'meta' => $meta,
        ]);
    }

    public function switchOver(string $description, ?Model $reference = null, ?int $userId = null): void
    {
        $this->histories()->create([
            'change_type' => QuotaHistory::TYPE_SWITCH_OVER,
            'quantity_change' => 0,
            'occurred_on' => now()->toDateString(),
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $userId,
        ]);
    }

    public function updateStatus(): void
    {
        if ($this->forecast_remaining === 0 && $this->actual_remaining === 0) {
            $this->status = self::STATUS_DEPLETED;
            $this->is_active = false;
        } elseif ($this->forecast_remaining <= ($this->total_allocation * 0.1)) {
            $this->status = self::STATUS_LIMITED;
        } else {
            $this->status = self::STATUS_AVAILABLE;
        }
    }

    public function matchesProduct(Product $product): bool
    {
        // Resolve PK from product if not explicitly set
        $pk = $product->pk_capacity;
        if ($pk === null) {
            try {
                $pk = app(\App\Services\HsCodeResolver::class)->resolveForProduct($product);
            } catch (\Throwable $e) {
                $pk = null;
            }
        }

        if ($pk === null) {
            // Without PK info we cannot decide strictly; treat as not matching to avoid wrong allocation
            return false;
        }

        // Prefer normalized columns if present
        $min = $this->min_pk; $max = $this->max_pk;
        $minIncl = (bool) ($this->is_min_inclusive ?? true);
        $maxIncl = (bool) ($this->is_max_inclusive ?? true);

        // Fallback parse from label if columns are null
        if ($min === null && $max === null && !empty($this->government_category)) {
            try {
                $parsed = \App\Support\PkCategoryParser::parse((string) $this->government_category);
                $min = $parsed['min_pk'];
                $max = $parsed['max_pk'];
                $minIncl = (bool)($parsed['min_incl'] ?? true);
                $maxIncl = (bool)($parsed['max_incl'] ?? true);
            } catch (\Throwable $e) {}
        }

        // No range data means it matches all
        if ($min === null && $max === null) { return true; }

        $pk = (float) $pk;
        if ($min !== null) {
            $minOk = $minIncl ? ($pk >= (float)$min) : ($pk > (float)$min);
            if (!$minOk) { return false; }
        }
        if ($max !== null) {
            $maxOk = $maxIncl ? ($pk <= (float)$max) : ($pk < (float)$max);
            if (!$maxOk) { return false; }
        }
        return true;
    }
}
