<?php

namespace App\Enums;

enum AttendanceType: string
{
    case Masuk = 'masuk';
    case Pulang = 'pulang';

    public function label(): string
    {
        return match ($this) {
            self::Masuk => 'Masuk',
            self::Pulang => 'Pulang',
        };
    }
}
