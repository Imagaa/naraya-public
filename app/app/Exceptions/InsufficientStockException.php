<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(int $available, int $requested)
    {
        parent::__construct("Stok tidak cukup: tersedia {$available}, diminta {$requested}.");
    }
}
