<?php

namespace App\Events;

class QuotaImportCompleted
{
    /** @var string|int */
    public $periodKey;

    /**
     * @param string|int $periodKey
     */
    public function __construct($periodKey)
    {
        $this->periodKey = $periodKey;
    }
}

