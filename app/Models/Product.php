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
        'sap_model',
        'category',
        'pk_capacity',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pk_capacity' => 'float',
    ];

    public function quotaMappings()
    {
        return $this->hasMany(ProductQuotaMapping::class);
    }

    public function quotas()
    {
        return $this->belongsToMany(Quota::class, 'product_quota_mappings')
            ->withPivot(['priority', 'is_primary', 'notes'])
            ->orderByPivot('priority');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
