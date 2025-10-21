<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number','po_date','supplier','currency','note','published_at','created_by'
    ];

    protected $casts = [
        'po_date' => 'date',
        'published_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(PoLine::class, 'po_header_id');
    }
}

