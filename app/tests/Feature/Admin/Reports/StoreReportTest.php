<?php

use App\Enums\BusinessLine;
use App\Enums\BuyerStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Admin\Concerns\HasStorePeriod;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use App\Support\BusinessDay;
use App\Support\Reports\StoreReport;
use App\Support\Sales\Consignments;
use App\Support\Sales\SalesNotes;
use Illuminate\Support\Carbon;
use Livewire\Component;

// Store report figures
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $store = function (string $name, bool $account, BuyerStatus $status = BuyerStatus::Approved) {
        $user = User::factory()->create(['role' => Role::User, 'name' => $name, 'password' => $account ? 'rahasia123' : null]);
        BuyerProfile::factory()->create(['user_id' => $user->id, 'sales_user_id' => $this->sales->id, 'status' => $status, 'consignment_opened_at' => null]);

        return $user;
    };
    $this->a = $store('Toko Anggrek', false);
    $this->b = $store('Toko Bougenvil', true);
    $this->pending = $store('Toko Calon', false, BuyerStatus::Pending);
    $this->toyA = Product::factory()->create(['name' => 'Kartu Mainan', 'line' => BusinessLine::Toys, 'price' => 1000, 'unit' => 'pcs', 'pieces_per_unit' => 1]);
    $this->toyB = Product::factory()->create(['name' => 'Yoyo', 'line' => BusinessLine::Toys, 'price' => 2000, 'unit' => 'pcs', 'pieces_per_unit' => 1]);
    $this->notes = app(SalesNotes::class);
    $this->sell = fn (User $store, Product $p, int $pcs, NotePaymentMethod $m = NotePaymentMethod::Cash) => $this->notes
        ->record($this->sales, BusinessLine::Toys, $store->id, [$p->id => $pcs], $m, null, null, 'bawaan');
    $this->october = [BusinessDay::boundsFor('2026-10-01')[0], BusinessDay::boundsFor('2026-10-31')[1]];

    // Toko Anggrek, October (all times UTC; WIB = +7)
    $this->travelTo(Carbon::parse('2026-10-20 03:00'));
    ($this->sell)($this->a, $this->toyA, 10);                                         // cash 10.000, visit Oct 20
    Visit::create(['sales_id' => $this->sales->id, 'buyer_id' => $this->a->id, 'visited_at' => '2026-10-20 05:00']); // same WIB day
    $this->travelTo(Carbon::parse('2026-10-21 03:00'));
    ($this->sell)($this->a, $this->toyB, 5, NotePaymentMethod::Transfer);              // transfer 10.000, visit Oct 21
    $rejected = ($this->sell)($this->a, $this->toyA, 7, NotePaymentMethod::Transfer);  // rejected below: never counts
    $this->notes->reject($rejected, $this->admin, 'Bukti salah');
    $this->travelTo(Carbon::parse('2026-10-22 03:00'));
    app(Consignments::class)->open($this->sales, $this->a->id, BusinessLine::Toys, [$this->toyA->id => 50]);
    app(Consignments::class)->visit($this->sales, $this->a->id, BusinessLine::Toys, [$this->toyA->id => 30], [], NotePaymentMethod::Cash); // 20 sold, 30 back
    ($this->sell)($this->pending, $this->toyA, 99);                                   // a pending sign-up: never in the figures

    $this->travelTo(Carbon::parse('2026-10-23 03:00')); // Friday 10:00 WIB
    $this->report = app(StoreReport::class);
});

it('sums one store\'s period', function () {
    expect($this->report->summary(BusinessLine::Toys, ...[...$this->october, $this->a->id]))->toBe([
        'terjual' => 35, 'uang' => 40000, 'tunai' => 30000, 'transfer' => 10000,
        'kunjungan' => 3, 'dibawa_kembali' => 30, 'masih_tertitip' => 0,
    ])->and($this->report->lastVisitAt($this->a->id)?->toDateTimeString())->toBe('2026-10-22 03:00:00');
});

it('splits per item with a total', function () {
    $items = $this->report->items(BusinessLine::Toys, ...[...$this->october, $this->a->id]);

    expect($items->map(fn ($r) => [$r->product->name, $r->terjual, $r->uang, $r->masih_tertitip, $r->dibawa_kembali])->all())->toBe([
        ['Kartu Mainan', 30, 30000, 0, 30],
        ['Yoyo', 5, 10000, 0, 0],
    ])->and($items->sum('terjual'))->toBe(35)
        ->and($items->sum('uang'))->toBe(40000);
});

it('sums all approved stores and counts accounts', function () {
    expect($this->report->summary(BusinessLine::Toys, ...$this->october))->toMatchArray(['terjual' => 35, 'uang' => 40000, 'kunjungan' => 3])
        ->and($this->report->storeCounts(BusinessLine::Toys, ...$this->october))
        ->toBe(['total' => 2, 'dengan_penjualan' => 1, 'berakun' => 1, 'tanpa_akun' => 1]);
});

it('ranks stores, idle ones included, by each sort', function () {
    $names = fn (string $sort) => $this->report->ranking(BusinessLine::Toys, ...[...$this->october, $sort])->getCollection()->pluck('name')->all();
    $rows = $this->report->ranking(BusinessLine::Toys, ...$this->october)->getCollection()->keyBy('name');

    expect($names('uang'))->toBe(['Toko Anggrek', 'Toko Bougenvil'])
        ->and($names('terjual'))->toBe(['Toko Anggrek', 'Toko Bougenvil'])
        ->and($names('lama'))->toBe(['Toko Bougenvil', 'Toko Anggrek'])       // never visited comes first
        ->and($rows['Toko Anggrek'])->terjual->toBe(35)->uang->toBe(40000)->kunjungan->toBe(3)->masih_tertitip->toBe(0)
        ->and($rows['Toko Bougenvil'])->terjual->toBe(0)->uang->toBe(0)->kunjungan->toBe(0)->kunjungan_terakhir->toBeNull();
});

it('puts 23:30 WIB on the last day in that month', function () {
    $this->travelTo(Carbon::parse('2026-09-30 16:30')); // 23:30 WIB, Sep 30
    ($this->sell)($this->b, $this->toyB, 4);
    $september = [BusinessDay::boundsFor('2026-09-01')[0], BusinessDay::boundsFor('2026-09-30')[1]];

    expect($this->report->summary(BusinessLine::Toys, ...[...$september, $this->b->id])['terjual'])->toBe(4)
        ->and($this->report->summary(BusinessLine::Toys, ...[...$this->october, $this->b->id])['terjual'])->toBe(0);
});

it('resolves period presets in WIB', function () {
    $page = new class extends Component { use HasStorePeriod; };
    $page->mountHasStorePeriod();
    $wib = fn () => array_map(fn (Carbon $c) => $c->copy()->timezone(BusinessDay::zone())->format('Y-m-d H:i'), $page->bounds());

    expect($page->period)->toBe('month')->and($wib())->toBe(['2026-10-01 00:00', '2026-10-23 23:59']);
    $page->setPeriod('today');
    expect($wib())->toBe(['2026-10-23 00:00', '2026-10-23 23:59']);
    $page->setPeriod('7d');
    expect($wib())->toBe(['2026-10-17 00:00', '2026-10-23 23:59']);
    $page->setPeriod('range');
    $page->fromMonth = '2026-08';
    $page->toMonth = '2026-09';
    expect($wib())->toBe(['2026-08-01 00:00', '2026-09-30 23:59'])
        ->and($page->periodLabel())->toBe('Agt – Sep 2026');
});
