<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductQuotaMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quota_id',
        'priority',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'bool',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function quota()
    {
        return $this->belongsTo(Quota::class);
    }
}
