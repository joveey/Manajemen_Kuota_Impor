<?php

namespace App\Services\Exceptions;

use Exception;

class InsufficientQuotaException extends Exception
{
    public static function forProduct(string $productName, int $requestedQuantity): self
    {
        return new self(
            sprintf(
                'Kuota tidak mencukupi untuk produk %s (permintaan %d unit)',
                $productName,
                $requestedQuantity
            )
        );
    }
}
