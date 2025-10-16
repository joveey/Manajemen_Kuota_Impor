<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'row_index',
        'raw_json',
        'normalized_json',
        'errors_json',
        'status',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'normalized_json' => 'array',
        'errors_json' => 'array',
    ];

    public function import()
    {
        return $this->belongsTo(Import::class, 'import_id');
    }
}

