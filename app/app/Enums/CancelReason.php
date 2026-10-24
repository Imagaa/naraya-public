<?php

namespace App\Enums;

/** Why admin cancelled an order. */
enum CancelReason: string
{
    case StokHabis = 'stok_habis';
    case TokoMintaBatal = 'toko_minta_batal';
    case AlamatTakTerjangkau = 'alamat_tak_terjangkau';
    case PembayaranBermasalah = 'pembayaran_bermasalah';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::StokHabis => 'Stok habis',
            self::TokoMintaBatal => 'Toko minta batal',
            self::AlamatTakTerjangkau => 'Alamat tak terjangkau',
            self::PembayaranBermasalah => 'Pembayaran bermasalah',
            self::Lainnya => 'Lainnya',
        };
    }
}
