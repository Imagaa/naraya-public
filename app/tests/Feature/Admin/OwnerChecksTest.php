<?php

use App\Enums\AttendanceType;
use App\Enums\BusinessLine;
use App\Enums\CashDepositStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\ConsignmentMove;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentWithdrawal;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesProfile;
use App\Models\SalesTrackPoint;
use App\Models\User;
use App\Support\Sales\SalesNotes;
use Illuminate\Support\Carbon;

// Owner checks + digests: once per subject, never repeated
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 03:00')); // Friday 10.00 WIB
    $this->owner = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    SalesProfile::factory()->create(['user_id' => $this->sales->id, 'is_active' => true, 'joined_at' => '2026-10-01']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Anggrek']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
    $this->titles = fn () => $this->owner->fresh()->notifications->map(fn ($n) => $n->data['title'].' | '.$n->data['body'])->all();
    $this->check = fn () => $this->artisan('admin:notify-checks')->assertSuccessful();
});

it('nags once about an order left unconfirmed for 12 hours and a handover left for 24', function () {
    $new = Order::factory()->create(['buyer_id' => $this->store->id, 'status' => OrderStatus::New, 'created_at' => now()->subHours(13)]);
    Order::factory()->create(['buyer_id' => $this->store->id, 'status' => OrderStatus::New, 'created_at' => now()->subHours(11)]); // not yet
    $stuck = Order::factory()->create(['buyer_id' => $this->store->id, 'status' => OrderStatus::Processing, 'sales_id' => $this->sales->id, 'sales_assigned_at' => now()->subHours(25)]);

    ($this->check)();
    ($this->check)();

    $titles = collect(($this->titles)());
    expect($titles->filter(fn ($t) => str_starts_with($t, 'Order belum dikonfirmasi'))->values()->all())->toBe(["Order belum dikonfirmasi | #{$new->id} Toko Anggrek — lebih dari 12 jam"])
        ->and($titles)->toContain("Barang belum diserahkan ke sales | #{$stuck->id} Toko Anggrek · Budi — lebih dari 24 jam");
});

it('tells when a sales person has not checked in an hour after the schedule, and not on a day off', function () {
    $this->travelTo(Carbon::parse('2026-10-23 01:59')); // 08.59 WIB, schedule 08.00
    ($this->check)();
    expect(($this->titles)())->toBeEmpty();

    $this->travelTo(Carbon::parse('2026-10-23 02:01')); // 09.01
    ($this->check)();
    ($this->check)();
    expect(($this->titles)())->toBe(['Belum absen masuk | Budi — jadwal 08.00']);
});

it('watches an open shift: GPS gap over 30 minutes, still open at 21.00, longer than 12 hours', function () {
    $masuk = Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => Carbon::parse('2026-10-23 00:30')]);
    SalesTrackPoint::create(['attendance_id' => $masuk->id, 'sales_id' => $this->sales->id, 'latitude' => -7.6, 'longitude' => 111.5, 'accuracy' => 10, 'recorded_at' => Carbon::parse('2026-10-23 02:20')]);
    ($this->check)(); // 10.00 WIB: last point 09.20 → 40 min gap
    ($this->check)();

    $this->travelTo(Carbon::parse('2026-10-23 14:05')); // 21.05 WIB, open since 07.30
    ($this->check)();

    $titles = ($this->titles)();
    expect(collect($titles)->filter(fn ($t) => str_starts_with($t, 'GPS sales mati'))->count())->toBe(1) // one gap (last point 09.20), told once
        ->and($titles)->toContain('GPS sales mati | Budi — tidak ada sinyal sejak 09.20', 'Lupa absen pulang | Budi masuk 07.30, belum absen pulang', 'Jam kerja lebih dari 12 jam | Budi bekerja sejak 07.30');
});

it('chases deposits, investment cash, proofs, distributions and withdrawals past their time', function () {
    CashDeposit::factory()->create(['sales_id' => $this->sales->id, 'status' => CashDepositStatus::Submitted, 'claimed_amount' => 50000, 'created_at' => now()->subHours(25)]);
    $base = ['buyer_id' => $this->store->id, 'quantity' => 1, 'unit_price' => 700000, 'monthly_return' => 28000, 'months' => 12, 'amount' => 700000, 'source' => 'toys'];
    InvestmentPurchase::create($base + ['payment_method' => 'cash_sales', 'sales_id' => $this->sales->id, 'status' => 'dibayar', 'created_at' => now()->subHours(25), 'verified_at' => now()->subHours(25)]);
    InvestmentPurchase::create($base + ['payment_method' => 'transfer', 'status' => 'menunggu_verifikasi', 'created_at' => now()->subHours(25)]);
    InvestmentPurchase::create($base + ['payment_method' => 'transfer', 'status' => 'dibayar', 'verified_at' => now()->subHours(49), 'created_at' => now()->subDays(3)]);
    InvestmentWithdrawal::create(['buyer_id' => $this->store->id, 'amount' => 100000, 'bank_name' => 'BNI', 'account_number' => '123', 'account_name' => 'Ani', 'status' => 'menunggu', 'created_at' => now()->subHours(25)]);

    ($this->check)();
    ($this->check)();

    $titles = ($this->titles)();
    foreach (['Setoran belum diverifikasi', 'Uang investasi belum disetor', 'Bukti investasi belum diverifikasi', 'Paket belum disalurkan ke mitra', 'Penarikan belum ditransfer'] as $title) {
        expect(collect($titles)->filter(fn ($t) => str_starts_with($t, $title))->count())->toBe(1, $title);
    }
});

it('tells when store returns sit with a sales person for more than 3 days', function () {
    $toy = Product::factory()->create(['line' => BusinessLine::Toys]);
    ConsignmentMove::create(['buyer_id' => $this->store->id, 'product_id' => $toy->id, 'line' => BusinessLine::Toys, 'sales_id' => $this->sales->id, 'kind' => 'retur', 'quantity' => -6, 'created_at' => now()->subDays(4)]);

    ($this->check)();
    ($this->check)();

    expect(($this->titles)())->toContain('Retur menumpuk di sales | Budi memegang 6 pcs retur toko lebih dari 3 hari');
});

it('sends the H-1 payout reminder, the daily recap and the weekly recap', function () {
    InvestmentPurchase::create(['buyer_id' => $this->store->id, 'quantity' => 2, 'unit_price' => 700000, 'monthly_return' => 28000, 'months' => 12, 'amount' => 1400000,
        'payment_method' => 'transfer', 'source' => 'toys', 'status' => 'aktif', 'activated_at' => now()->subMonth(), 'next_payout_on' => '2026-10-24']);
    $toy = Product::factory()->create(['line' => BusinessLine::Toys, 'price' => 1000, 'unit' => 'pcs', 'pieces_per_unit' => 1]);
    app(SalesNotes::class)->record($this->sales, BusinessLine::Toys, $this->store->id, [$toy->id => 30], NotePaymentMethod::Cash, null, null, 'bawaan');

    $this->artisan('admin:payout-reminder')->assertSuccessful();
    $this->artisan('admin:payout-reminder')->assertSuccessful();
    $this->artisan('admin:daily-recap')->assertSuccessful();
    $this->artisan('admin:weekly-recap')->assertSuccessful();

    $titles = ($this->titles)();
    expect(collect($titles)->filter(fn ($t) => str_starts_with($t, 'Besok bagi hasil'))->values()->all())->toBe(['Besok bagi hasil | 1 toko · Rp 56.000'])
        ->and(collect($titles)->first(fn ($t) => str_starts_with($t, 'Rekap hari ini')))->toContain('Omzet Rp 30.000')->toContain('1 nota')
        ->and(collect($titles)->first(fn ($t) => str_starts_with($t, 'Rekap minggu ini')))->toContain('Toko Anggrek');
});
