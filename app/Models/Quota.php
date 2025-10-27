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
        if (is_null($product->pk_capacity)) {
            return true;
        }

        $pattern = '/([0-9.,]+)\s*PK\s*-\s*([0-9.,]+)\s*PK/i';
        if (preg_match($pattern, $this->government_category, $matches)) {
            $min = (float) str_replace(',', '.', $matches[1]);
            $max = (float) str_replace(',', '.', $matches[2]);

            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }

            return $product->pk_capacity >= $min && $product->pk_capacity <= $max;
        }

        return true;
    }
}
