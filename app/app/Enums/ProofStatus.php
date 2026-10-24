<?php

namespace App\Enums;

/** Admin's review of a transfer/QRIS proof photo on a sales note. */
enum ProofStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }

    /** x-admin.pill tone */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'new',
            self::Verified => 'ok',
            self::Rejected => 'bad',
        };
    }

    /** x-ui.tag variant (sales portal) */
    public function salesVariant(): string
    {
        return match ($this) {
            self::Pending => 'accent',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }
}
