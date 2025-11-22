<?php

namespace App\Services\Exceptions;

use Exception;

class InsufficientQuotaException extends Exception
{
    public static function forProduct(string $productName, int $requestedQuantity): self
    {
        return new self(
            sprintf(
                'Insufficient quota for product %s (requested %d units)',
                $productName,
                $requestedQuantity
            )
        );
    }
}
