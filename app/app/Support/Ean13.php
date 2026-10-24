<?php

namespace App\Support;

final class Ean13
{
    public static function isValid(string $code): bool
    {
        if (! preg_match('/^\d{13}$/', $code)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $code[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === (int) $code[12];
    }
}
