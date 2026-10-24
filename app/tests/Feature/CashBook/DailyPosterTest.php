<?php

use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Enums\CashBookSource;
use App\Enums\CashDepositStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CashBookEntry;
use App\Models\CashBookSetting;
use App\Models\CashDeposit;
use App\Models\Order;
use App\Services\CashBook\DailyPoster;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-10-06 09:00')));

function rekap(CashBookSource $source, CashBookEntryType $type): ?CashBookEntry
{
    return CashBookEntry::where('kind', CashBookEntryKind::Rekap)->where('source', $source)->where('type', $type)->first();
}

it('summarises a day\'s online payments, deposits, shortfalls, surpluses and refunds', function () {
    $day = Carbon::parse('2026-10-05 10:00');
    $paid = Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => $day, 'total' => 120000]);
    Order::factory()->create(['payment_method' => 'cash', 'payment_status' => PaymentStatus::Paid, 'paid_at' => $day, 'total' => 999]); // cash never counts here
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => $day->copy()->subDay(), 'total' => 999]); // other day
    $ok = CashDeposit::factory()->create(['status' => CashDepositStatus::Verified, 'verified_at' => $day, 'expected_amount' => 50000, 'claimed_amount' => 50000]);
    $short = CashDeposit::factory()->create(['status' => CashDepositStatus::Resolved, 'resolved_at' => $day, 'expected_amount' => 30000, 'claimed_amount' => 25000]);
    $over = CashDeposit::factory()->create(['status' => CashDepositStatus::Resolved, 'resolved_at' => $day, 'expected_amount' => 10000, 'claimed_amount' => 12000]);
    $refund = Order::factory()->create(['status' => OrderStatus::Cancelled, 'refund_status' => 'done', 'refunded_at' => $day, 'total' => 40000]);

    expect(app(DailyPoster::class)->postDay($day))->toBe(5);

    expect(rekap(CashBookSource::PenjualanOnline, CashBookEntryType::Pemasukan))
        ->amount->toBe(120000)->catatan->toBe('Penjualan online · 1 transaksi')
        ->date->toDateString()->toBe('2026-10-05')->rekap_date->toDateString()->toBe('2026-10-05');
    expect(rekap(CashBookSource::SetoranSales, CashBookEntryType::Pemasukan)->amount)->toBe(90000); // expected amounts
    expect(rekap(CashBookSource::SelisihSetoran, CashBookEntryType::Pengeluaran)->amount)->toBe(5000);
    expect(rekap(CashBookSource::SelisihSetoran, CashBookEntryType::Pemasukan)->amount)->toBe(2000);
    expect(rekap(CashBookSource::RefundOrder, CashBookEntryType::Pengeluaran)->amount)->toBe(40000);
    expect(rekap(CashBookSource::PenjualanOnline, CashBookEntryType::Pemasukan)->items->pluck('subject_id')->all())->toBe([$paid->id]);
    // deposits net to what was actually received: 50000 + 25000 + 12000
    expect(90000 - 5000 + 2000)->toBe($ok->claimed_amount + $short->claimed_amount + $over->claimed_amount);
});

it('is idempotent when a day is posted twice', function () {
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => '2026-10-05 10:00', 'total' => 1000]);
    $poster = app(DailyPoster::class);

    $poster->postDay(Carbon::parse('2026-10-05'));
    expect($poster->postDay(Carbon::parse('2026-10-05')))->toBe(0);
    expect(CashBookEntry::count())->toBe(1);
});

it('fills missed days up to yesterday and moves the pointer', function () {
    CashBookSetting::current()->update(['posted_through' => '2026-10-02']);
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => '2026-10-03 08:00', 'total' => 1000]);
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => '2026-10-05 08:00', 'total' => 2000]);
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => '2026-10-06 08:00', 'total' => 4000]); // today: not yet

    expect(app(DailyPoster::class)->postPending())->toBe(2);
    expect(CashBookSetting::current()->posted_through->toDateString())->toBe('2026-10-05');
    expect(CashBookEntry::sum('amount'))->toBe(3000);
});

it('previews today without storing anything', function () {
    Order::factory()->create(['payment_method' => 'gopay', 'payment_status' => PaymentStatus::Paid, 'paid_at' => '2026-10-06 08:00', 'total' => 4000]);

    $today = app(DailyPoster::class)->summarize(now());

    expect($today)->toHaveCount(1)->and($today[0]['amount'])->toBe(4000)->and(CashBookEntry::count())->toBe(0);
});

it('runs from the scheduler command', function () {
    CashBookSetting::current()->update(['posted_through' => '2026-10-04']);
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => '2026-10-05 08:00', 'total' => 1000]);

    $this->artisan('cashbook:post-daily')->assertSuccessful();
    expect(CashBookEntry::count())->toBe(1);
});

it('no longer writes a cash book row when an order completes or a deposit is verified', function () {
    expect(method_exists(Order::class, 'recordCashBookIncome'))->toBeFalse()
        ->and(method_exists(CashDeposit::class, 'recordCashBookIncome'))->toBeFalse();
});
