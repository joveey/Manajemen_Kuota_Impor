<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoLineVoyageSplit extends Model
{
    use HasFactory;

    protected $table = 'po_line_voyage_splits';

    protected $fillable = [
        'po_line_id','seq_no','qty',
        'voyage_bl','voyage_etd','voyage_eta','voyage_factory','voyage_status','voyage_remark','created_by',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'voyage_etd' => 'date',
        'voyage_eta' => 'date',
    ];

    public function line()
    {
        return $this->belongsTo(PoLine::class, 'po_line_id');
    }
}

