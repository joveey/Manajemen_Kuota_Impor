<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_header_id','line_no','model_code','item_desc','hs_code_id','qty_ordered','qty_received','uom','eta_date','validation_status','validation_notes',
        'warehouse_code','warehouse_name','warehouse_source','subinventory_code','subinventory_name','subinventory_source','amount','category_code','category','material_group','sap_order_status'
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:2',
        'qty_received' => 'decimal:2',
        'eta_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function header()
    {
        return $this->belongsTo(PoHeader::class, 'po_header_id');
    }
}
