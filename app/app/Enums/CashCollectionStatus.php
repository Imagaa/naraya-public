<?php

namespace App\Enums;

enum CashCollectionStatus: string
{
    case Held = 'held';
    case Deposited = 'deposited';
}
