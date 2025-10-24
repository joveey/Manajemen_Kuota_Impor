<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_no','line_no','invoice_no','invoice_date','qty'
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'qty' => 'decimal:2',
    ];
}

