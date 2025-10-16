<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MappingVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'period_key',
        'version',
        'notes',
    ];
}

