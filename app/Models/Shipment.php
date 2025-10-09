<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Shipment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shipment_number',
        'purchase_order_id',
        'parent_shipment_id',
        'quantity_planned',
        'quantity_received',
        'ship_date',
        'eta_date',
        'receipt_date',
        'status',
        'detail',
        'auto_generated',
    ];

    protected $casts = [
        'ship_date' => 'date',
        'eta_date' => 'date',
        'receipt_date' => 'date',
        'auto_generated' => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment) {
            if (empty($shipment->shipment_number)) {
                $shipment->shipment_number = 'SHIP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4));
            }
        });

        static::saved(function (Shipment $shipment) {
            $shipment->purchaseOrder?->refreshAggregates();
        });
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function parentShipment()
    {
        return $this->belongsTo(Shipment::class, 'parent_shipment_id');
    }

    public function childShipments()
    {
        return $this->hasMany(Shipment::class, 'parent_shipment_id');
    }

    public function receipts()
    {
        return $this->hasMany(ShipmentReceipt::class);
    }

    public function recordReceipt(int $quantity, \DateTimeInterface $receiptDate, ?string $notes = null, ?string $documentNumber = null, ?int $userId = null): void
    {
        $quantity = min($quantity, $this->quantity_planned - $this->quantity_received);
        if ($quantity <= 0) {
            return;
        }

        $this->receipts()->create([
            'receipt_date' => $receiptDate->format('Y-m-d'),
            'quantity_received' => $quantity,
            'notes' => $notes,
            'document_number' => $documentNumber,
            'created_by' => $userId,
        ]);

        $totalReceived = (int) $this->receipts()->sum('quantity_received');
        $this->quantity_received = min($totalReceived, $this->quantity_planned);
        $this->receipt_date = $receiptDate;
        $this->status = $this->quantity_received >= $this->quantity_planned
            ? self::STATUS_DELIVERED
            : self::STATUS_PARTIAL;

        $this->save();
    }
}
