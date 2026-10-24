<?php

namespace App\Observers;

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Notifications\AdminNotice;
use App\Support\Sales\Shift;

/** The owner hears every check-in and check-out. */
class AttendanceObserver
{
    /** From 17.00 WIB a check-in is past working hours. */
    public const LATE_HOUR = 17;

    public function created(Attendance $a): void
    {
        $name = $a->salesUser?->name ?? 'Sales';
        $at = $a->created_at->wib();
        $url = route('admin.attendances.index', absolute: false);

        if ($a->tipe === AttendanceType::Masuk) {
            $late = $at->hour >= self::LATE_HOUR;
            AdminNotice::toAdmins($late ? 'Absen masuk terlambat' : 'Absen masuk', "{$name} · {$at->format('H.i')}".($late ? ' — sudah lewat jam kerja' : ''), $url, 'log-in', $late ? 'warning' : 'info');

            return;
        }

        $masuk = Attendance::where('sales_id', $a->sales_id)->where('tipe', AttendanceType::Masuk)
            ->where('created_at', '<=', $a->created_at)->latest('created_at')->first();
        $day = '';
        if ($masuk) {
            $r = Shift::recap($masuk);
            $notes = (int) collect($r['stores'])->sum('notes');
            $day = ' · '.number_format($r['km'], 1, ',', '.').' km · '.count($r['stores']).' toko · '.$notes.' nota · Rp '.number_format($r['total'], 0, ',', '.');
            $day = str_replace(' 0,0 km', ' 0 km', $day);
        }
        AdminNotice::toAdmins('Absen pulang', "{$name} · {$at->format('H.i')}{$day}", $url, 'log-out');
    }
}
