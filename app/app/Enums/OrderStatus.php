<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new'; // Baru
    case Processing = 'processing'; // Diproses
    case Shipped = 'shipped'; // Dikirim
    case Completed = 'completed'; // Selesai
    case RejectedByUser = 'rejected_by_user'; // Ditolak-User
    case Cancelled = 'cancelled'; // Dibatalkan

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Processing => 'Diproses',
            self::Shipped => 'Dikirim',
            self::Completed => 'Selesai',
            self::RejectedByUser => 'Ditolak User',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
