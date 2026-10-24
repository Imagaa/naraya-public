<?php

namespace App\Services\CashBook;

use App\Enums\BusinessLine;
use App\Enums\ProofStatus;
use App\Models\SalesNote;
use App\Support\BusinessDay;
use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Enums\CashBookSource;
use App\Enums\CashDepositStatus;
use App\Models\CashBookEntry;
use App\Models\CashBookSetting;
use App\Models\CashDeposit;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One cash book line per source per day. Deposits count at
 * the amount the sales should hand over; a shortfall/surplus is its own line,
 * so the net equals the cash actually received and the gap stays visible.
 * Each business line posts into its own book;
 * marketplace orders are Naraya's.
 */
class DailyPoster
{
    private const UNITS = [
        'penjualan_online' => 'transaksi',
        'setoran_sales' => 'setoran',
        'selisih_setoran' => 'setoran',
        'refund_order' => 'order',
        'penjualan_non_tunai' => 'nota',
    ];

    /** @return list<array{source: CashBookSource, type: CashBookEntryType, count: int, amount: int, items: list<array{0: \Illuminate\Database\Eloquent\Model, 1: int}>}> */
    public function summarize(CarbonInterface $day, BusinessLine $line = BusinessLine::Naraya): array
    {
        $range = BusinessDay::boundsFor($day);
        $naraya = $line === BusinessLine::Naraya;

        $online = $naraya ? Order::where('payment_method', '!=', 'cash')->whereBetween('paid_at', $range)->get() : collect();
        $nonCash = SalesNote::forLine($line)->where('proof_status', ProofStatus::Verified)->whereBetween('proof_reviewed_at', $range)->get();
        $deposits = CashDeposit::forLine($line)->where(fn ($w) => $w
            ->where(fn ($q) => $q->where('status', CashDepositStatus::Verified)->whereBetween('verified_at', $range))
            ->orWhere(fn ($q) => $q->where('status', CashDepositStatus::Resolved)->whereBetween('resolved_at', $range)))
            ->get();
        $refunds = $naraya ? Order::where('refund_status', 'done')->whereBetween('refunded_at', $range)->get() : collect();

        $groups = [
            [CashBookSource::PenjualanOnline, CashBookEntryType::Pemasukan, $online->map(fn ($o) => [$o, (int) $o->total])],
            [CashBookSource::PenjualanNonTunai, CashBookEntryType::Pemasukan, $nonCash->map(fn ($n) => [$n, (int) $n->total])],
            [CashBookSource::SetoranSales, CashBookEntryType::Pemasukan, $deposits->map(fn ($d) => [$d, (int) $d->expected_amount])],
            [CashBookSource::SelisihSetoran, CashBookEntryType::Pengeluaran, $deposits->filter(fn ($d) => $d->claimed_amount < $d->expected_amount)->map(fn ($d) => [$d, (int) ($d->expected_amount - $d->claimed_amount)])],
            [CashBookSource::SelisihSetoran, CashBookEntryType::Pemasukan, $deposits->filter(fn ($d) => $d->claimed_amount > $d->expected_amount)->map(fn ($d) => [$d, (int) ($d->claimed_amount - $d->expected_amount)])],
            [CashBookSource::RefundOrder, CashBookEntryType::Pengeluaran, $refunds->map(fn ($o) => [$o, (int) $o->total])],
        ];

        $out = [];
        foreach ($groups as [$source, $type, $items]) {
            $items = $items->filter(fn ($i) => $i[1] > 0)->values()->all();
            if ($items !== []) {
                $out[] = ['source' => $source, 'type' => $type, 'count' => count($items), 'amount' => array_sum(array_column($items, 1)), 'items' => $items];
            }
        }

        return $out;
    }

    public function postDay(CarbonInterface $day): int
    {
        $day = Carbon::parse($day->format('Y-m-d'));

        return DB::transaction(function () use ($day) {
            $created = 0;

            foreach (BusinessLine::cases() as $line) {
                // A day inside an already-closed month (long downtime) posts today, marked late.
                $late = Ledger::isClosed($day, $line);

                foreach ($this->summarize($day, $line) as $group) {
                    $exists = CashBookEntry::withTrashed()->forLine($line)->where('kind', CashBookEntryKind::Rekap)
                        ->where('source', $group['source'])->where('type', $group['type'])
                        ->whereDate('rekap_date', $day)->exists();
                    if ($exists) {
                        continue;
                    }

                    $label = $group['source'] === CashBookSource::SelisihSetoran
                        ? 'Selisih setoran ('.($group['type'] === CashBookEntryType::Pengeluaran ? 'kurang' : 'lebih').')'
                        : $group['source']->label();

                    $entry = CashBookEntry::create([
                        'line' => $line,
                        'type' => $group['type'],
                        'kind' => CashBookEntryKind::Rekap,
                        'source' => $group['source'],
                        'rekap_date' => $day->toDateString(),
                        'date' => $late ? BusinessDay::today()->toDateString() : $day->toDateString(),
                        'amount' => $group['amount'],
                        'catatan' => $label.' · '.$group['count'].' '.self::UNITS[$group['source']->value]
                            .($late ? ' · rekap terlambat untuk '.$day->locale('id')->translatedFormat('j M Y') : ''),
                    ]);
                    $entry->items()->createMany(array_map(fn ($i) => [
                        'subject_type' => $i[0]->getMorphClass(),
                        'subject_id' => $i[0]->getKey(),
                        'amount' => $i[1],
                    ], $group['items']));
                    $created++;
                }
            }

            return $created;
        });
    }

    /** Every day after the last posted one, up to yesterday; safe to run any number of times. */
    public function postPending(): int
    {
        $setting = CashBookSetting::current();
        $day = ($setting->posted_through ?? BusinessDay::today()->subDays(2))->copy()->addDay();
        $created = 0;

        while ($day->toDateString() < BusinessDay::today()->toDateString()) {
            $created += $this->postDay($day);
            $setting->update(['posted_through' => $day->toDateString()]);
            $day->addDay();
        }

        return $created;
    }
}
