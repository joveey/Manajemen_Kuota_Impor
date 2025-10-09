<?php

namespace App\Services;

use App\Models\Quota;

class QuotaAllocationResult
{
    public function __construct(
        public Quota $selectedQuota,
        public ?Quota $initialQuota = null
    ) {
    }

    public function switched(): bool
    {
        return $this->initialQuota && $this->initialQuota->isNot($this->selectedQuota);
    }
}
