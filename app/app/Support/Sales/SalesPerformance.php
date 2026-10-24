<?php

namespace App\Support\Sales;

use App\Enums\BusinessLine;
use App\Enums\CashDepositStatus;
use App\Models\CashDeposit;
use App\Models\DeliveryRoute;
use App\Models\SalesNote;
use App\Models\User;
use App\Models\Visit;
use App\Support\AttendanceReport;
use App\Support\BusinessDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One sales person's month on one page (admin Sales): attendance and day
 * offs, store rounds, routes, notes, deposits, and the cash still owed.
 * Both business lines together. The month runs up to today.
 */
final class SalesPerformance
{
    public function month(User $sales, CarbonInterface $month): array
    {
        $start = Carbon::parse($month->format('Y-m-01'), BusinessDay::zone());
        $end = $start->copy()->endOfMonth()->startOfDay()->min(BusinessDay::today());
        $range = [BusinessDay::boundsFor($start)[0], BusinessDay::boundsFor($end)[1]];
        $attendance = AttendanceReport::month($sales, $start)['totals'];

        // A "round" is one store on one WIB day; a note always logs a visit, so visits cover both.
        $visits = Visit::where('sales_id', $sales->id)->whereBetween('visited_at', $range)->get(['buyer_id', 'visited_at']);
        $rounds = $visits->map(fn ($v) => $v->visited_at->wib()->format('Y-m-d').'#'.$v->buyer_id)->unique()->count();

        $notes = SalesNote::where('sales_id', $sales->id)->whereBetween('created_at', $range);
        $deposits = CashDeposit::where('sales_id', $sales->id)->whereBetween('created_at', $range)->get();
        $settled = $deposits->whereIn('status', [CashDepositStatus::Verified, CashDepositStatus::Resolved]);

        return [
            'hari_kerja' => $attendance['hari_kerja'],
            'hadir' => $attendance['hadir'],
            'terlambat' => $attendance['terlambat'],
            'tidak_absen' => $attendance['tidak_absen'],
            'libur' => $attendance['libur_diambil'],
            'kunjungan_toko' => $rounds,
            'toko_per_hari' => $attendance['hari_kerja'] ? round($rounds / $attendance['hari_kerja'], 1) : 0.0,
            'toko_unik' => $visits->pluck('buyer_id')->unique()->count(),
            'rute_selesai' => DeliveryRoute::where('sales_id', $sales->id)->where('status', 'finished')->whereBetween('finished_at', $range)->count(),
            'nota' => (clone $notes)->count(),
            'penjualan' => (int) (clone $notes)->sum('total'),
            'setoran' => $deposits->count(),
            'setoran_terverifikasi' => (int) $settled->sum('claimed_amount'),
            'setoran_menunggu' => $deposits->whereIn('status', [CashDepositStatus::Submitted, CashDepositStatus::Disputed])->count(),
            'selisih_setoran' => (int) $settled->sum(fn ($d) => max(0, $d->expected_amount - $d->claimed_amount)),
            // what they still owe from this month's goods and notes (Pencocokan), both lines
            'kewajiban' => (int) collect(BusinessLine::cases())->sum(fn ($line) => app(Reconciliation::class)->forSales($sales, $line, $start, $end)['owed']),
        ];
    }
}
