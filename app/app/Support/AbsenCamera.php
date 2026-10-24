<?php

namespace App\Support;

use App\Enums\AttendanceType;
use App\Models\AttendanceLocation;
use App\Models\User;

/** What the absenCamera script (resources/js/attendance.js) needs for a check-in or check-out sheet. */
final class AbsenCamera
{
    /** @param array<int, int> $items check-in: product_id => qty taken from the warehouse */
    public static function config(User $sales, AttendanceType $tipe, array $items = []): array
    {
        return [
            'tipe' => $tipe->value,
            'tipeLabel' => $tipe->label(),
            'salesName' => $sales->name,
            'maxAkurasi' => (new AttendanceCalendar)->setting()->max_akurasi_m,
            'locations' => AttendanceLocation::allowedFor($sales)->map(fn ($l) => ['nama' => $l->nama, 'lat' => $l->lat, 'lng' => $l->lng, 'radius_m' => $l->radius_m])->values(),
            'items' => (object) $items,
            'sessionUrl' => route('sales.absensi.session'),
            'submitUrl' => route('sales.absensi.submit'),
            'doneUrl' => route('sales.home'),
        ];
    }
}
