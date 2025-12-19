<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrReceipt extends Model
{
    use HasFactory;

    protected $table = 'gr_receipts';

    protected $fillable = [
        'po_no','line_no','invoice_no','receive_date','qty','gr_unique','cat_po','cat_po_desc',
        'item_name','vendor_code','vendor_name','wh_code','wh_name','sloc_code','sloc_name','currency','amount','deliv_amount',
        'mat_doc','cat','cat_desc'
    ];

    protected $casts = [
        'receive_date' => 'date',
        'qty' => 'decimal:2',
    ];
}
