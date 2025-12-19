<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

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
        // New canonical columns
        'po_doc',
        'created_date',
        'vendor_no',
        'vendor_name',
        'line_no',
        'item_code',
        'item_desc',
        'wh_code',
        'wh_name',
        'subinv_code',
        'subinv_name',
        'wh_source',
        'subinv_source',
        'qty',
        'cat_po',
        'cat_desc',
        'mat_grp',
        // Legacy aliases (still accepted via mutators)
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
        'created_date' => 'date',
        'sequence_number' => 'integer',
        'amount' => 'decimal:2',
        'qty' => 'decimal:2',
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

    public function allocatedQuotas()
    {
        return $this->belongsToMany(Quota::class, 'purchase_order_quota')
            ->withPivot(['allocated_qty'])
            ->withTimestamps();
    }

    // -------- Accessors/Mutators to bridge legacy names --------
    public function getPoNumberAttribute(): ?string
    {
        return $this->po_doc;
    }

    public function setPoNumberAttribute($value): void
    {
        $this->attributes['po_doc'] = $value;
    }

    public function getOrderDateAttribute(): ?Carbon
    {
        return $this->created_date instanceof Carbon
            ? $this->created_date
            : ($this->created_date ? Carbon::parse($this->created_date) : null);
    }

    public function setOrderDateAttribute($value): void
    {
        $this->attributes['created_date'] = $value;
    }

    public function getVendorNumberAttribute(): ?string
    {
        return $this->vendor_no;
    }

    public function setVendorNumberAttribute($value): void
    {
        $this->attributes['vendor_no'] = $value;
    }

    public function getLineNumberAttribute(): ?string
    {
        return $this->line_no;
    }

    public function setLineNumberAttribute($value): void
    {
        $this->attributes['line_no'] = $value;
    }

    public function getItemDescriptionAttribute(): ?string
    {
        return $this->item_desc;
    }

    public function setItemDescriptionAttribute($value): void
    {
        $this->attributes['item_desc'] = $value;
    }

    public function getWarehouseCodeAttribute(): ?string
    {
        return $this->wh_code;
    }

    public function setWarehouseCodeAttribute($value): void
    {
        $this->attributes['wh_code'] = $value;
    }

    public function getWarehouseNameAttribute(): ?string
    {
        return $this->wh_name;
    }

    public function setWarehouseNameAttribute($value): void
    {
        $this->attributes['wh_name'] = $value;
    }

    public function getWarehouseSourceAttribute(): ?string
    {
        return $this->wh_source;
    }

    public function setWarehouseSourceAttribute($value): void
    {
        $this->attributes['wh_source'] = $value;
    }

    public function getSubinventoryCodeAttribute(): ?string
    {
        return $this->subinv_code;
    }

    public function setSubinventoryCodeAttribute($value): void
    {
        $this->attributes['subinv_code'] = $value;
    }

    public function getSubinventoryNameAttribute(): ?string
    {
        return $this->subinv_name;
    }

    public function setSubinventoryNameAttribute($value): void
    {
        $this->attributes['subinv_name'] = $value;
    }

    public function getSubinventorySourceAttribute(): ?string
    {
        return $this->subinv_source;
    }

    public function setSubinventorySourceAttribute($value): void
    {
        $this->attributes['subinv_source'] = $value;
    }

    public function getQuantityAttribute(): ?float
    {
        return $this->qty !== null ? (float) $this->qty : null;
    }

    public function setQuantityAttribute($value): void
    {
        $this->attributes['qty'] = $value;
    }

    public function getCategoryAttribute(): ?string
    {
        return $this->cat_desc;
    }

    public function setCategoryAttribute($value): void
    {
        $this->attributes['cat_desc'] = $value;
    }

    public function getCategoryCodeAttribute(): ?string
    {
        return $this->cat_po;
    }

    public function setCategoryCodeAttribute($value): void
    {
        $this->attributes['cat_po'] = $value;
    }

    public function getMaterialGroupAttribute(): ?string
    {
        return $this->mat_grp;
    }

    public function setMaterialGroupAttribute($value): void
    {
        $this->attributes['mat_grp'] = $value;
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity - (int) $this->quantity_received);
    }

    public function refreshAggregates(): void
    {
        $shipped = (int) $this->shipments()->sum('quantity_planned');

        // Prefer GR receipts (SAP actuals) as the single source of truth for received quantity.
        // Fall back to shipment receipts if no GR data exists for this PO.
        $receivedFromGr = DB::table('gr_receipts')
            ->where('po_no', $this->po_number)
            ->sum('qty');

        if ($receivedFromGr > 0) {
            $received = (int) $receivedFromGr;
        } else {
            $received = (int) $this->shipments()->sum('quantity_received');
        }

        $this->quantity_shipped   = $shipped;
        $this->quantity_received  = $received;

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
