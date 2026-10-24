<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending'; // Menunggu
    case Paid = 'paid'; // Berhasil
    case Failed = 'failed'; // Gagal

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Berhasil',
            self::Failed => 'Gagal',
        };
    }
}
