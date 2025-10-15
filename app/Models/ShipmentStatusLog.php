<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'status',
        'description',
        'quantity_planned_snapshot',
        'quantity_received_snapshot',
        'recorded_at',
        'meta',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}

