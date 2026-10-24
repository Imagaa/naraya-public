<?php

namespace App\Enums;

enum ComplaintType: string
{
    case TolakSaatTerima = 'tolak_saat_terima'; // Tolak saat terima (3.1)
    case KomplainPascaSelesai = 'komplain_pasca_selesai'; // Komplain pasca-Selesai (3.4)

    public function label(): string
    {
        return match ($this) {
            self::TolakSaatTerima => 'Tolak Saat Terima',
            self::KomplainPascaSelesai => 'Komplain Pasca-Selesai',
        };
    }
}
