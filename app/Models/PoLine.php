<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_header_id','line_no','model_code','item_desc','hs_code_id','qty_ordered','qty_received','uom','eta_date','validation_status','validation_notes'
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:2',
        'qty_received' => 'decimal:2',
        'eta_date' => 'date',
    ];

    public function header()
    {
        return $this->belongsTo(PoHeader::class, 'po_header_id');
    }
}
