<?php

namespace App\Enums;

enum StockTransferKind: string
{
    case Masuk = 'masuk';   // goods received into the warehouse (admin)
    case Ambil = 'ambil';   // a sales person takes goods from the warehouse
    case Retur = 'retur';   // a sales person returns goods to the warehouse
    case Opname = 'opname'; // admin counts a sales person's goods; the items are the shortfall
    case ReturToko = 'retur_toko'; // store returns (titip jual) handed to the warehouse at check-out; not sellable stock

    public function label(): string
    {
        return match ($this) {
            self::Masuk => 'Barang masuk',
            self::Ambil => 'Pengambilan',
            self::Retur => 'Retur ke gudang',
            self::Opname => 'Selisih cek stok',
            self::ReturToko => 'Retur dari toko',
        };
    }

    /** x-admin.pill tone */
    public function tone(): string
    {
        return match ($this) {
            self::Masuk => 'ok',
            self::Ambil => 'info',
            self::Retur => 'ship',
            self::Opname => 'bad',
            self::ReturToko => 'new',
        };
    }
}
