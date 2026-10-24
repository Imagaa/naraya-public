<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** What the admin reads: one day across all sales, one month for one sales. */
final class AttendanceReport
{
    /** @return Collection<int, array{sales: User, scheduled: bool, masuk: ?Attendance, pulang: ?Attendance, state: string, day_off: ?\App\Models\SalesDayOff}> */
    public static function day(CarbonInterface $date): Collection
    {
        $calendar = new AttendanceCalendar;
        $records = Attendance::with('location')->whereBetween('created_at', BusinessDay::boundsFor($date))->get()->groupBy('sales_id');
        $sales = User::activeSales()->with('salesProfile.area')->orderBy('name')->get();
        // anyone who checked in that day but is no longer active still belongs on that day's page
        $sales = $sales->concat(User::with('salesProfile.area')->whereIn('id', $records->keys()->diff($sales->pluck('id')))->get());

        return $sales->map(function (User $s) use ($records, $calendar, $date) {
            $day = $records->get($s->id, collect());
            $masuk = $day->firstWhere('tipe', AttendanceType::Masuk);

            return [
                'sales' => $s,
                'scheduled' => $calendar->for($s, $date) !== null,
                'masuk' => $masuk,
                'pulang' => $day->firstWhere('tipe', AttendanceType::Pulang),
                'state' => $calendar->dayState($s, $date, $masuk),
                'day_off' => $calendar->dayOffOn($s, $date),
            ];
        })->values();
    }

    /** One sales, one month, up to today. */
    public static function month(User $sales, CarbonInterface $month): array
    {
        $calendar = new AttendanceCalendar;
        $start = Carbon::parse($month->format('Y-m-01'), BusinessDay::zone());
        $last = $start->copy()->endOfMonth()->startOfDay()->min(BusinessDay::today());

        $records = Attendance::with('location')
            ->where('sales_id', $sales->id)
            ->whereBetween('created_at', [BusinessDay::boundsFor($start)[0], BusinessDay::boundsFor($start->copy()->endOfMonth())[1]])
            ->get()
            ->groupBy(fn ($a) => $a->created_at->wib()->format('Y-m-d'));

        $days = [];
        for ($d = $start->copy(); $d->lte($last); $d->addDay()) {
            $recs = $records->get($d->format('Y-m-d'), collect());
            $masuk = $recs->firstWhere('tipe', AttendanceType::Masuk);
            $days[] = [
                'date' => $d->copy(),
                'schedule' => $calendar->for($sales, $d),
                'masuk' => $masuk,
                'pulang' => $recs->firstWhere('tipe', AttendanceType::Pulang),
                'state' => $calendar->dayState($sales, $d, $masuk),
                'day_off' => $calendar->dayOffOn($sales, $d),
            ];
        }

        // Totals are over working days only: an off-day check-in shows in the table but doesn't inflate "hadir".
        $c = collect($days)->filter(fn ($d) => $d['schedule'] !== null);
        $hadir = $c->whereIn('state', ['hadir', 'terlambat'])->count();
        $late = $c->where('state', 'terlambat');

        return ['days' => $days, 'totals' => [
            'hari_kerja' => $c->count(),
            'libur_diambil' => collect($days)->whereNotNull('day_off')->count(),
            'hadir' => $hadir,
            'terlambat' => $late->count(),
            'menit_terlambat' => (int) $late->sum(fn ($d) => $d['masuk']->selisih_menit ?? 0),
            'tidak_absen' => $c->where('state', 'tidak_absen')->count(),
            'pulang_cepat' => $c->filter(fn ($d) => $d['pulang']?->status === AttendanceStatus::PulangCepat)->count(),
            'persen_tepat' => $hadir ? (int) round(($hadir - $late->count()) / $hadir * 100) : null,
        ]];
    }

    /** @return Collection<int, array{sales: User, totals: array}> most-late first */
    public static function ranking(CarbonInterface $month): Collection
    {
        return User::activeSales()->orderBy('name')->get()
            ->map(fn (User $s) => ['sales' => $s, 'totals' => self::month($s, $month)['totals']])
            ->sortByDesc(fn ($r) => [$r['totals']['terlambat'], $r['totals']['tidak_absen']])
            ->values();
    }

    /** @return list<array{label: string, terlambat: int}> oldest first */
    public static function trend(User $sales, CarbonInterface $month, int $months = 6): array
    {
        return collect(range($months - 1, 0))
            ->map(function ($back) use ($sales, $month) {
                $m = Carbon::parse($month->format('Y-m-01'), BusinessDay::zone())->subMonthsNoOverflow($back);

                return ['label' => $m->locale('id')->translatedFormat('M y'), 'terlambat' => self::month($sales, $m)['totals']['terlambat']];
            })
            ->all();
    }

    public static function note(array $t, ?array $previous): string
    {
        $text = $t['terlambat'] > 0
            ? "Terlambat {$t['terlambat']}x dari {$t['hari_kerja']} hari kerja (rata-rata ".(int) round($t['menit_terlambat'] / $t['terlambat']).' mnt)'
            : "Tidak pernah terlambat dari {$t['hari_kerja']} hari kerja";
        $text .= ", {$t['tidak_absen']}x tidak absen.";

        if ($previous !== null && $previous['terlambat'] !== $t['terlambat']) {
            $text .= ($t['terlambat'] < $previous['terlambat'] ? ' Membaik' : ' Memburuk')." dibanding bulan lalu ({$previous['terlambat']}x).";
        }

        return $text;
    }
}
