<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotaHistory extends Model
{
    use HasFactory;

    public const TYPE_FORECAST_DECREASE = 'forecast_decrease';
    public const TYPE_FORECAST_INCREASE = 'forecast_increase';
    public const TYPE_ACTUAL_DECREASE = 'actual_decrease';
    public const TYPE_ACTUAL_INCREASE = 'actual_increase';
    public const TYPE_SWITCH_OVER = 'switch_over';
    public const TYPE_MANUAL = 'manual_adjustment';

    protected $fillable = [
        'quota_id',
        'change_type',
        'quantity_change',
        'occurred_on',
        'reference_type',
        'reference_id',
        'description',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'meta' => 'array',
    ];

    public function quota()
    {
        return $this->belongsTo(Quota::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
