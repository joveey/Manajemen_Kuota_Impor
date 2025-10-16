<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class PurchaseOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ORDERED = 'ordered';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'sequence_number',
        'period',
        'po_number',
        'sap_reference',
        'vendor_number',
        'vendor_name',
        'line_number',
        'item_code',
        'item_description',
        'product_id',
        'quota_id',
        'quantity',
        'amount',
        'quantity_shipped',
        'quantity_received',
        'order_date',
        'pgi_branch',
        'customer_name',
        'pic_name',
        'status_po_display',
        'truck',
        'moq',
        'category',
        'category_code',
        'material_group',
        'sap_order_status',
        'warehouse_code',
        'warehouse_name',
        'warehouse_source',
        'subinventory_code',
        'subinventory_name',
        'subinventory_source',
        'plant_name',
        'plant_detail',
        'status',
        'forecast_deducted_at',
        'actual_completed_at',
        'created_by',
        'remarks',
    ];

    protected $casts = [
        'order_date' => 'date',
        'sequence_number' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function quota()
    {
        return $this->belongsTo(Quota::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function quotaHistories()
    {
        return $this->morphMany(QuotaHistory::class, 'reference');
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->quantity_received);
    }

    public function refreshAggregates(): void
    {
        $shipped = (int) $this->shipments()->sum('quantity_planned');
        $received = (int) $this->shipments()->sum('quantity_received');

        $this->quantity_shipped = $shipped;
        $this->quantity_received = $received;

        if ($received >= $this->quantity) {
            $this->status = self::STATUS_COMPLETED;
            $this->actual_completed_at = now();
        } elseif ($received > 0) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($shipped > 0) {
            $this->status = self::STATUS_IN_TRANSIT;
        } else {
            $this->status = self::STATUS_ORDERED;
        }

        $this->save();
    }
}
