<?php

namespace App\Observers;

use App\Models\Quota;

class QuotaObserver
{
    /**
     * Ensure quota status reflects remaining quantity.
     */
    public function saving(Quota $quota): void
    {
        if (! $quota->isDirty('actual_remaining')) {
            return;
        }

        $remaining = (int) $quota->actual_remaining;

        $quota->status = $remaining > 0 ? 'available' : 'depleted';
    }
}

