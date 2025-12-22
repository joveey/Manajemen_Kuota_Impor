<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PeriodSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'period_key',
        'period_start',
        'period_end',
        'last_synced_at',
        'meta',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public static function record(string $module, Carbon $start, Carbon $end, array $meta = []): self
    {
        $key = $start->format('Y-m');

        return static::updateOrCreate(
            ['module' => $module, 'period_key' => $key],
            [
                'period_start' => $start->copy(),
                'period_end' => $end->copy(),
                'last_synced_at' => Carbon::now(),
                'meta' => $meta ?: null,
            ]
        );
    }

    public static function lastFor(string $module, Carbon $start): ?self
    {
        $key = $start->format('Y-m');

        return static::where('module', $module)
            ->where('period_key', $key)
            ->orderByDesc('last_synced_at')
            ->first();
    }
}
