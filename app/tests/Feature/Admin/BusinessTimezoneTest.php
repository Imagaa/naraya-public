<?php

use App\Enums\CashBookEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Admin\Dashboard\Index as Dashboard;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Orders\Show as OrderShow;
use App\Livewire\Admin\Reports\Penjualan;
use App\Models\CashBookEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\CashBook\DailyPoster;
use App\Services\CashBook\Ledger;
use App\Support\BusinessDay;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// Timestamps are stored in UTC; days, schedules and displayed times follow WIB (config app.business_timezone).

beforeEach(fn () => $this->admin = User::factory()->create(['role' => Role::Admin]));

it('turns a WIB calendar day into UTC bounds', function () {
    [$start, $end] = BusinessDay::boundsFor('2026-10-06');

    expect($start->toDateTimeString())->toBe('2026-10-05 17:00:00')->and($end->toDateTimeString())->toBe('2026-10-06 16:59:59');
});

it('counts an early-morning WIB payment on its WIB day in the daily summary', function () {
    $this->travelTo(Carbon::parse('2026-10-07 01:00'));
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => '2026-10-05 20:00:00', 'total' => 1000]); // 6 Oct 03:00 WIB

    expect(app(DailyPoster::class)->postDay(Carbon::parse('2026-10-05')))->toBe(0);
    expect(app(DailyPoster::class)->postDay(Carbon::parse('2026-10-06')))->toBe(1);
});

it('uses the WIB date as today for entries and corrections', function () {
    $this->travelTo(Carbon::parse('2026-10-05 18:00')); // 6 Oct 01:00 WIB

    $e = app(Ledger::class)->record(['type' => CashBookEntryType::Pemasukan, 'amount' => 1000, 'date' => '2026-10-06', 'catatan' => 'Subuh'], $this->admin);
    $k = app(Ledger::class)->correct($e, null, 'Salah', $this->admin);

    expect($k->date->toDateString())->toBe('2026-10-06');
});

it('runs the cash book schedule on WIB midnight', function () {
    \Illuminate\Support\Facades\Artisan::call('schedule:list'); // the schedule is registered when the console boots
    $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'cashbook:'));

    expect($events)->toHaveCount(2);
    $events->each(fn ($e) => expect($e->timezone)->toBe('Asia/Jakarta'));
});

it('counts today\'s sales on the WIB day on the dashboard', function () {
    $this->travelTo(Carbon::parse('2026-10-05 19:00')); // 6 Oct 02:00 WIB
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => '2026-10-05 18:00:00', 'total' => 1000]); // 6 Oct 01:00 WIB → today
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => '2026-10-05 16:00:00', 'total' => 300]);  // 5 Oct 23:00 WIB → yesterday

    Livewire::actingAs($this->admin)->test(Dashboard::class)->assertViewHas('salesToday', 1000)->assertViewHas('salesYesterday', 300);
});

it('shows order dates in WIB', function () {
    Order::factory()->create(['created_at' => '2026-10-05 20:00:00']); // 6 Oct 03:00 WIB

    Livewire::actingAs($this->admin)->test(OrdersIndex::class)->assertSee('6 Okt');
});

it('filters reports by WIB days', function () {
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => '2026-10-05 20:00:00', 'total' => 1000]); // 6 Oct 03:00 WIB

    $rows = Livewire::actingAs($this->admin)->test(Penjualan::class)->set('startDate', '2026-10-06')->set('endDate', '2026-10-06')->instance()->rows();

    expect($rows)->toHaveCount(1);
});
