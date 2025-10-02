<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'group',
        'description'
    ];

    /**
     * Relasi dengan Role
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withTimestamps();
    }

    /**
     * Get permissions grouped by group
     */
    public static function getGrouped(): array
    {
        return self::all()
            ->groupBy('group')
            ->map(fn($items) => $items->pluck('display_name', 'name'))
            ->toArray();
    }
}