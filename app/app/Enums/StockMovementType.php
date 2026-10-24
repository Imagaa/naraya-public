<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Correction = 'correction';
    case WriteOff = 'write_off';
}
