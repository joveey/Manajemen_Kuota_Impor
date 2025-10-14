<?php

namespace App\Models;

use App\Models\ShipmentStatusLog;
use Carbon\Carbon;
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

            if (empty($shipment->status)) {
                $shipment->status = static::initialStatusFor($shipment->ship_date);
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

    public function statusLogs()
    {
        return $this->hasMany(ShipmentStatusLog::class)->orderByDesc('recorded_at');
    }

    public static function initialStatusFor($shipDate): string
    {
        if ($shipDate instanceof Carbon) {
            return $shipDate->isFuture() ? self::STATUS_PENDING : self::STATUS_IN_TRANSIT;
        }

        if ($shipDate instanceof \DateTimeInterface) {
            return Carbon::parse($shipDate)->isFuture() ? self::STATUS_PENDING : self::STATUS_IN_TRANSIT;
        }

        if ($shipDate) {
            return Carbon::parse($shipDate)->isFuture()
                ? self::STATUS_PENDING
                : self::STATUS_IN_TRANSIT;
        }

        return self::STATUS_IN_TRANSIT;
    }

    public function syncScheduledStatus(?string $description = null): bool
    {
        if ($this->quantity_received > 0) {
            return false;
        }

        $expected = $this->ship_date && $this->ship_date->isFuture()
            ? self::STATUS_PENDING
            : self::STATUS_IN_TRANSIT;

        if ($expected !== $this->status) {
            $this->status = $expected;
            $this->save();
            $this->logStatus($expected, $description ?? 'Status diperbarui otomatis berdasarkan jadwal pengiriman.');

            return true;
        }

        return false;
    }

    public function computeStatus(): string
    {
        if ($this->quantity_planned > 0 && $this->quantity_received >= $this->quantity_planned) {
            return self::STATUS_DELIVERED;
        }

        if ($this->quantity_received > 0) {
            return self::STATUS_PARTIAL;
        }

        return $this->ship_date && $this->ship_date->isFuture()
            ? self::STATUS_PENDING
            : self::STATUS_IN_TRANSIT;
    }

    public function logStatus(string $status, ?string $description = null, bool $force = false): void
    {
        $latestStatus = $this->statusLogs()
            ->latest('recorded_at')
            ->value('status');

        if (!$force && $latestStatus === $status) {
            return;
        }

        $this->statusLogs()->create([
            'status' => $status,
            'description' => $description,
            'quantity_planned_snapshot' => $this->quantity_planned,
            'quantity_received_snapshot' => $this->quantity_received,
            'recorded_at' => now(),
        ]);
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

        $description = $documentNumber
            ? sprintf('Penerimaan %s unit (dokumen: %s).', number_format($quantity), $documentNumber)
            : sprintf('Penerimaan %s unit.', number_format($quantity));

        $this->logStatus($this->status, $description, true);
    }
}
