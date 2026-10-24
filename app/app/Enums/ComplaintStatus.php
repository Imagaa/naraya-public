<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case Diajukan = 'diajukan';
    case Diresolusi = 'diresolusi'; // disetujui + diresolusi digabung satu aksi Admin (lihat PRD 3.4)
    case Ditutup = 'ditutup'; // ditolak + ditutup digabung satu aksi Admin

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Diresolusi => 'Diresolusi',
            self::Ditutup => 'Ditutup',
        };
    }
}
