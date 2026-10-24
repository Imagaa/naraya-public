<?php

namespace App\Enums;

enum CashDepositStatus: string
{
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Disputed = 'disputed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Disputed => 'Sengketa Cash',
            self::Resolved => 'Terselesaikan',
        };
    }

    /** x-ui.tag variant for this status. */
    public function variant(): string
    {
        return match ($this) {
            self::Submitted => 'accent',
            self::Verified => 'success',
            self::Disputed => 'danger',
            self::Resolved => 'neutral',
        };
    }
}
