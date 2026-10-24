<?php

use App\Enums\AttendanceType;
use App\Enums\BusinessLine;
use App\Enums\CashDepositStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reward;
use App\Models\SalesProfile;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Support\DayOffs;
use App\Support\Orders\TransferPayments;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushChannel;

// Owner push notices fired by events (catalogue)
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 01:00')); // 08.00 WIB, Friday
    $this->owner = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    SalesProfile::factory()->create(['user_id' => $this->sales->id, 'is_active' => true]);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Anggrek']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
    $this->notices = fn () => $this->owner->fresh()->notifications->map(fn ($n) => $n->data['title'].' | '.$n->data['body'])->all();
});

it('pushes every owner notice, not only the urgent ones', function () {
    $this->owner->updatePushSubscription('https://push.example/abc', 'key', 'token');

    expect((new AdminNotice('Order baru', '#1', '/orders/1', 'shopping-cart'))->via($this->owner->fresh()))->toContain(WebPushChannel::class);
});

it('tells the owner when a transfer order cancels itself', function () {
    $order = Order::factory()->create(['buyer_id' => $this->store->id, 'status' => OrderStatus::New, 'payment_method' => 'transfer',
        'payment_status' => PaymentStatus::Pending, 'payment_expires_at' => now()->subMinute()]);
    app(TransferPayments::class)->expireDue();

    expect(($this->notices)())->toContain("Pesanan batal otomatis | #{$order->id} Toko Anggrek — bukti transfer tidak diunggah dalam 30 menit");
});

it('tells the owner when a store rejects the goods and when an order completes', function () {
    $order = Order::factory()->create(['buyer_id' => $this->store->id, 'status' => OrderStatus::Shipped, 'total' => 50000]);
    $order->update(['status' => OrderStatus::RejectedByUser]);
    $done = Order::factory()->create(['buyer_id' => $this->store->id, 'status' => OrderStatus::Shipped, 'total' => 75000]);
    $done->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);

    expect(($this->notices)())->toContain("Toko menolak barang | #{$order->id} Toko Anggrek", "Pesanan selesai | #{$done->id} Toko Anggrek · Rp 75.000");
});

it('reports check-in, a late check-in after 17.00 and check-out with the day', function () {
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => now()]);
    $this->travelTo(Carbon::parse('2026-10-23 10:30')); // 17.30 WIB
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Pulang, 'created_at' => now()]);
    $late = User::factory()->create(['role' => Role::Sales, 'name' => 'Sari']);
    Attendance::factory()->create(['sales_id' => $late->id, 'tipe' => AttendanceType::Masuk, 'created_at' => now()]);

    expect(($this->notices)())->toContain(
        'Absen masuk | Budi · 08.00',
        'Absen pulang | Budi · 17.30 · 0 km · 0 toko · 0 nota · Rp 0',
        'Absen masuk terlambat | Sari · 17.30 — sudah lewat jam kerja',
    );
});

it('reports a day off request, a transfer nota to check and a deposit shortfall', function () {
    app(DayOffs::class)->request($this->sales, '2026-10-26');
    $toy = Product::factory()->create(['line' => BusinessLine::Toys, 'price' => 1000, 'unit' => 'pcs', 'pieces_per_unit' => 1]);
    app(SalesNotes::class)->record($this->sales, BusinessLine::Toys, $this->store->id, [$toy->id => 5], NotePaymentMethod::Transfer, null, null, 'bawaan');
    $deposit = CashDeposit::factory()->create(['sales_id' => $this->sales->id, 'status' => CashDepositStatus::Submitted, 'expected_amount' => 100000, 'claimed_amount' => 90000]);
    $deposit->update(['status' => CashDepositStatus::Verified]);

    expect(($this->notices)())->toContain(
        'Pengajuan libur | Budi libur Senin, 26 Okt',
        'Nota transfer perlu dicek | Budi · Toko Anggrek · Rp 5.000',
        "Selisih setoran | Setoran #{$deposit->id} Budi kurang Rp 10.000",
    );
});

it('warns when stock runs low, a stock check is short and a reward runs out', function () {
    $product = Product::factory()->create(['name' => 'Yoyo', 'stock' => 50, 'min_stock' => 20, 'is_active' => true, 'unit' => 'pcs', 'pieces_per_unit' => 1, 'line' => BusinessLine::Toys]);
    $product->update(['stock' => 15]);
    $product->update(['stock' => 12]); // already low: no second notice
    $reward = Reward::factory()->create(['name' => 'Payung', 'stock' => 1]);
    $reward->update(['stock' => 0]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Toys, [$product->id => 10]);
    app(StockTransfers::class)->stockCheck($this->sales, BusinessLine::Toys, [$product->id => 7], $this->owner);

    $notices = ($this->notices)();
    expect(collect($notices)->filter(fn ($n) => str_starts_with($n, 'Stok menipis'))->values()->all())->toBe(['Stok menipis | Yoyo tinggal 15 pcs (batas 20)'])
        ->and($notices)->toContain('Stok hadiah habis | Payung', 'Selisih cek stok | Budi kurang 3 pcs');
});

it('sums the payouts credited today and reports finished packages', function () {
    $purchase = fn (int $paid, int $months) => \App\Models\InvestmentPurchase::create([
        'buyer_id' => $this->store->id, 'quantity' => 2, 'unit_price' => 700000, 'monthly_return' => 28000, 'months' => $months, 'amount' => 1400000,
        'payment_method' => 'transfer', 'source' => 'toys', 'status' => 'aktif', 'activated_at' => now()->subMonths($paid + 1),
        'months_paid' => $paid, 'next_payout_on' => now()->toDateString(),
    ]);
    $purchase(2, 12);
    $last = $purchase(11, 12);

    app(\App\Support\Investment\Investments::class)->payDue();

    expect(($this->notices)())->toContain(
        'Bagi hasil dikreditkan | 2 pembayaran · Rp 112.000 ke 1 toko',
        "Paket investasi selesai | Toko Anggrek · {$last->number()} · modal Rp 1.400.000 kembali",
    );
});

it('reports months the cash book closed by itself', function () {
    \App\Models\CashBookEntry::factory()->create(['date' => now()->subMonths(2)->startOfMonth()->toDateString()]);

    $closed = app(\App\Services\CashBook\Ledger::class)->closeDue();

    expect($closed)->toBeGreaterThan(0)
        ->and(collect(($this->notices)())->first(fn ($n) => str_starts_with($n, 'Buku kas ditutup')))->toContain('bulan ditutup otomatis');
});
