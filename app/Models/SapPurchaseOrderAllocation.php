<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SapPurchaseOrderAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_doc',
        'po_line_no',
        'po_line_no_raw',
        'item_code',
        'vendor_no',
        'vendor_name',
        'order_date',
        'product_id',
        'target_qty',
        'allocations',
        'period_key',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'target_qty' => 'float',
        'allocations' => 'array',
        'is_active' => 'bool',
        'last_seen_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getForecastBucket(): array
    {
        $data = $this->allocations ?? [];
        $forecast = Arr::get($data, 'forecast');
        if (is_array($forecast)) {
            return $forecast;
        }

        // Legacy fallback: allocations stored as flat map.
        if (is_array($data)) {
            $allScalar = collect($data)->filter(fn ($value, $key) => !is_array($value))->all();
            if (!empty($allScalar)) {
                return $allScalar;
            }
        }

        return [];
    }

    public function setForecastBucket(array $map): void
    {
        $data = $this->allocations ?? [];
        $data['forecast'] = $map;
        $this->allocations = $data;
    }

    public function getVoyageBucket(): array
    {
        $data = $this->allocations ?? [];
        return Arr::get($data, 'voyage', []);
    }

    public function setVoyageBucket(array $voyage): void
    {
        $data = $this->allocations ?? [];
        $data['voyage'] = $voyage;
        $this->allocations = $data;
    }

}
