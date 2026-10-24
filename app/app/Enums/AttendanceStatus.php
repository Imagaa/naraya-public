<?php

namespace App\Enums;

/**
 * How a check-in was judged, fixed at submit time. Since 2026-10-12 check-in has no set hours
 *: every new one on a working day is Tercatat; the timed
 * statuses remain for older records.
 */
enum AttendanceStatus: string
{
    case Tercatat = 'tercatat';
    case TepatWaktu = 'tepat_waktu';
    case Terlambat = 'terlambat';
    case PulangCepat = 'pulang_cepat';
    case TanpaJadwal = 'tanpa_jadwal';

    public function text(?int $menit = null): string
    {
        return match ($this) {
            self::Tercatat => 'Tercatat',
            self::TepatWaktu => 'Tepat waktu',
            self::Terlambat => "Terlambat {$menit} mnt",
            self::PulangCepat => "Pulang cepat {$menit} mnt",
            self::TanpaJadwal => 'Hari libur',
        };
    }

    /** x-admin.pill tone */
    public function tone(): string
    {
        return match ($this) {
            self::Tercatat, self::TepatWaktu => 'ok',
            self::Terlambat => 'bad',
            self::PulangCepat => 'new',
            self::TanpaJadwal => 'ship',
        };
    }

    /** x-ui.tag variant (sales portal) */
    public function salesVariant(): string
    {
        return match ($this) {
            self::Tercatat, self::TepatWaktu => 'success',
            self::Terlambat => 'danger',
            self::PulangCepat => 'accent',
            self::TanpaJadwal => 'neutral',
        };
    }
}
