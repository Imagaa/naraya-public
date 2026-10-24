<?php

namespace App\Enums;

/** Where an automatic daily summary entry comes from. */
enum CashBookSource: string
{
    case PenjualanOnline = 'penjualan_online';
    case SetoranSales = 'setoran_sales';
    case SelisihSetoran = 'selisih_setoran';
    case RefundOrder = 'refund_order';
    case PenjualanNonTunai = 'penjualan_non_tunai';

    public function label(): string
    {
        return match ($this) {
            self::PenjualanOnline => 'Penjualan online',
            self::SetoranSales => 'Setoran sales',
            self::SelisihSetoran => 'Selisih setoran',
            self::RefundOrder => 'Refund order',
            self::PenjualanNonTunai => 'Penjualan non-tunai',
        };
    }
}
