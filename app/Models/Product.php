<?php
// app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'model_type',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function quotas()
    {
        return $this->hasMany(Quota::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // Get current active quota
    public function currentQuota()
    {
        return $this->hasOne(Quota::class)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->where('status', '!=', 'exhausted');
    }
}

// app/Models/Quota.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quota extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quota_number',
        'product_id',
        'government_quantity',
        'forecast_quantity',
        'actual_quantity',
        'period_start',
        'period_end',
        'status',
        'notes'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function histories()
    {
        return $this->hasMany(QuotaHistory::class);
    }

    // Get remaining quota
    public function getRemainingAttribute()
    {
        return $this->forecast_quantity - $this->actual_quantity;
    }

    // Get usage percentage
    public function getUsagePercentageAttribute()
    {
        if ($this->forecast_quantity == 0) return 0;
        return round(($this->actual_quantity / $this->forecast_quantity) * 100, 2);
    }

    // Update status based on remaining quota
    public function updateStatus()
    {
        $remaining = $this->remaining;
        
        if ($remaining <= 0) {
            $this->status = 'exhausted';
        } elseif ($remaining < ($this->forecast_quantity * 0.1)) {
            $this->status = 'low';
        } else {
            $this->status = 'available';
        }
        
        $this->save();
    }

    // Check if quota is available
    public function isAvailable($quantity)
    {
        return $this->remaining >= $quantity;
    }
}

// app/Models/Factory.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Factory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'country',
        'contact_person',
        'phone',
        'email'
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}

// app/Models/PurchaseOrder.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'quota_id',
        'product_id',
        'factory_id',
        'quantity',
        'po_date',
        'status',
        'notes'
    ];

    protected $casts = [
        'po_date' => 'date'
    ];

    public function quota()
    {
        return $this->belongsTo(Quota::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    // Get total shipped quantity
    public function getTotalShippedAttribute()
    {
        return $this->shipments()->sum('quantity_shipped');
    }

    // Get total received quantity
    public function getTotalReceivedAttribute()
    {
        return $this->shipments()->sum('quantity_received');
    }

    // Check if fully shipped
    public function isFullyShipped()
    {
        return $this->total_shipped >= $this->quantity;
    }

    // Check if fully received
    public function isFullyReceived()
    {
        return $this->total_received >= $this->quantity;
    }
}

// app/Models/Shipment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shipment_number',
        'purchase_order_id',
        'quantity_shipped',
        'quantity_received',
        'shipment_date',
        'estimated_arrival',
        'actual_arrival',
        'status',
        'vessel_name',
        'port_origin',
        'port_destination',
        'shipping_details',
        'notes'
    ];

    protected $casts = [
        'shipment_date' => 'date',
        'estimated_arrival' => 'date',
        'actual_arrival' => 'date'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // Check if shipment is late
    public function isLate()
    {
        if ($this->status == 'completed') return false;
        return now()->gt($this->estimated_arrival);
    }

    // Get days until arrival
    public function daysUntilArrival()
    {
        if ($this->status == 'completed') return 0;
        return now()->diffInDays($this->estimated_arrival, false);
    }
}

// app/Models/QuotaHistory.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotaHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'quota_id',
        'action',
        'quantity_change',
        'balance_before',
        'balance_after',
        'user_id',
        'description'
    ];

    public function quota()
    {
        return $this->belongsTo(Quota::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
