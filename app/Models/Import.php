<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    use HasFactory;

    public const TYPE_HS_PK = 'hs_pk';
    public const TYPE_QUOTA = 'quota';

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_READY = 'ready';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'period_key',
        'source_filename',
        'stored_path',
        'status',
        'notes',
        'total_rows',
        'valid_rows',
        'error_rows',
        'created_by',
    ];

    public function items()
    {
        return $this->hasMany(ImportItem::class);
    }

    public function markAs(string $status, ?string $notes = null): void
    {
        $this->status = $status;
        if (!is_null($notes)) {
            $this->notes = $notes;
        }
        $this->save();
    }
}
