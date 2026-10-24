<?php

namespace App\Enums;

enum RedemptionStatus: string
{
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Diserahkan = 'diserahkan';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Diserahkan => 'Diserahkan',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
