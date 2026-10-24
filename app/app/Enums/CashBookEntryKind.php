<?php

namespace App\Enums;

enum CashBookEntryKind: string
{
    case Manual = 'manual';
    case Rekap = 'rekap';
    case Koreksi = 'koreksi';
}
