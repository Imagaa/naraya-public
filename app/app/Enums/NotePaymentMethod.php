<?php

namespace App\Enums;

/**
 * How a store paid a sales note. Cash is deposited by the sales person; a transfer goes straight to
 * Naraya's account (config naraya.transfer_to) with a proof photo. Since 2026-10-12 only cash and transfer
 * are offered (choices()); QRIS stays for notes made before.
 */
enum NotePaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
        };
    }

    /** @return list<self> what a sales person may choose today */
    public static function choices(): array
    {
        return [self::Cash, self::Transfer];
    }

    public function needsProof(): bool
    {
        return $this !== self::Cash;
    }
}
