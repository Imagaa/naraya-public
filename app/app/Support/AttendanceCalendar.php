<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSetting;
use App\Models\SalesDayOff;
use App\Models\SalesProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The one answer to "what applies to sales X on WIB day D".
 * Create one instance per request/run: it memoizes schedules, holidays and settings.
 */
final class AttendanceCalendar
{
    private ?Collection $schedules = null;

    private ?Collection $holidays = null;

    private ?AttendanceSetting $setting = null;

    private ?Collection $joined = null;

    private ?Collection $dayOffs = null;

    /** @return array{masuk: Carbon, pulang: Carbon}|null  null = not a working day */
    public function for(User|int $sales, CarbonInterface $date): ?array
    {
        $day = $this->wibDay($date);
        if ($this->holidayOn($day) !== null) {
            return null;
        }

        $id = $sales instanceof User ? $sales->id : $sales;
        $joined = $this->joinDates()->get($id);
        if ($joined !== null && $day->format('Y-m-d') < $joined) {
            return null; // not on the team yet
        }
        if ($this->dayOffOn($id, $day)) {
            return null; // their own weekly day off
        }

        $week = $this->schedules()->get($id) ?? $this->schedules()->get(0);
        $row = $week?->get($day->dayOfWeekIso);
        if (! $row || ! $row->jam_masuk || ! $row->jam_pulang) {
            return null;
        }

        return [
            'masuk' => $day->copy()->setTimeFromTimeString($row->jam_masuk),
            'pulang' => $day->copy()->setTimeFromTimeString($row->jam_pulang),
        ];
    }

    public function dayOffOn(User|int $sales, CarbonInterface $date): ?SalesDayOff
    {
        $this->dayOffs ??= SalesDayOff::active()->get()->keyBy(fn ($d) => $d->sales_id.'@'.$d->date->format('Y-m-d'));

        return $this->dayOffs->get(($sales instanceof User ? $sales->id : $sales).'@'.$this->wibDay($date)->format('Y-m-d'));
    }

    public function holidayOn(CarbonInterface $date): ?string
    {
        $this->holidays ??= AttendanceHoliday::all()->mapWithKeys(fn ($h) => [$h->tanggal->format('Y-m-d') => $h->nama]);

        return $this->holidays->get($this->wibDay($date)->format('Y-m-d'));
    }

    public function setting(): AttendanceSetting
    {
        return $this->setting ??= AttendanceSetting::current();
    }

    /**
     * Check-in has no set hours: on a working day any time is
     * Tercatat; the schedule only decides which days are working days.
     *
     * @return array{jadwal_jam: ?string, selisih_menit: ?int, status: AttendanceStatus}
     */
    public function judge(User|int $sales, AttendanceType $tipe, CarbonInterface $at): array
    {
        $day = $this->for($sales, Carbon::instance($at)->timezone(BusinessDay::zone()));

        return ['jadwal_jam' => null, 'selisih_menit' => null, 'status' => $day === null ? AttendanceStatus::TanpaJadwal : AttendanceStatus::Tercatat];
    }

    /** hadir | terlambat | tidak_absen | belum | libur */
    public function dayState(User|int $sales, CarbonInterface $date, ?Attendance $masuk): string
    {
        if ($masuk) {
            return $masuk->status === AttendanceStatus::Terlambat ? 'terlambat' : 'hadir';
        }

        $day = $this->for($sales, $date);
        if ($day === null) {
            return 'libur';
        }

        // no set hours: a working day only counts as missed once it is over
        return BusinessDay::today()->gt($day['masuk']->copy()->startOfDay()) ? 'tidak_absen' : 'belum';
    }

    /** Today's schedule and check-ins for the Beranda countdown and the in-app popup. */
    public function today(User $sales): array
    {
        $now = BusinessDay::now();
        $day = $this->for($sales, $now);
        $records = Attendance::where('sales_id', $sales->id)->whereBetween('created_at', BusinessDay::bounds())->get();
        $batas = $day ? $day['masuk']->copy()->addMinutes($this->setting()->toleransi_menit) : null;
        $fmt = fn (?Attendance $a) => $a ? [
            'jam' => $a->created_at->wib()->format('H:i'),
            'status' => $a->statusText(),
            'variant' => $a->status?->salesVariant() ?? 'neutral',
        ] : null;

        return [
            'now' => now()->toIso8601String(),
            'tanggal' => $now->format('Y-m-d'),
            'libur' => $day === null,
            'masuk_at' => $day ? $day['masuk']->toIso8601String() : null,
            'masuk_jam' => $day ? $day['masuk']->format('H:i') : null,
            'batas_at' => $batas?->toIso8601String(),
            'batas_jam' => $batas?->format('H:i'),
            'pulang_at' => $day ? $day['pulang']->toIso8601String() : null,
            'pulang_jam' => $day ? $day['pulang']->format('H:i') : null,
            'pengingat_menit' => $this->setting()->pengingat_menit,
            'masuk' => $fmt($records->firstWhere('tipe', AttendanceType::Masuk)),
            'pulang' => $fmt($records->firstWhere('tipe', AttendanceType::Pulang)),
        ];
    }

    private function wibDay(CarbonInterface $date): Carbon
    {
        return Carbon::instance($date)->timezone(BusinessDay::zone())->startOfDay();
    }

    /** user_id => 'Y-m-d' the sales joined */
    private function joinDates(): Collection
    {
        return $this->joined ??= SalesProfile::whereNotNull('joined_at')->get(['user_id', 'joined_at'])
            ->mapWithKeys(fn ($p) => [$p->user_id => $p->joined_at->format('Y-m-d')]);
    }

    /** user_id (0 = general) => weekday => row */
    private function schedules(): Collection
    {
        return $this->schedules ??= AttendanceSchedule::all()
            ->groupBy(fn ($s) => $s->user_id ?? 0)
            ->map(fn ($rows) => $rows->keyBy('weekday'));
    }
}
