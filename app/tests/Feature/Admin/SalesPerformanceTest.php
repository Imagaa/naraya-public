<?php

use App\Enums\BusinessLine;
use App\Enums\CashDepositStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Admin\Sales\Form;
use App\Livewire\Admin\Sales\Index;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\Product;
use App\Models\SalesProfile;
use App\Models\User;
use App\Models\Visit;
use App\Support\DayOffs;
use App\Support\Sales\DeliveryRoutes;
use App\Support\Sales\SalesNotes;
use App\Support\Sales\SalesPerformance;
use App\Support\Stock\StockTransfers;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 03:00')); // Wednesday 10:00 WIB
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    SalesProfile::factory()->create(['user_id' => $this->sales->id, 'joined_at' => '2026-10-01', 'is_active' => true]);
    [$a, $b] = collect(range(1, 2))->map(function () {
        $store = User::factory()->create(['role' => Role::User]);
        BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id]);

        return $store;
    })->all();

    // rounds: 2 stores on Oct 12, the same store twice on Oct 13 (counts once)
    Visit::create(['sales_id' => $this->sales->id, 'buyer_id' => $a->id, 'visited_at' => '2026-10-12 03:00']);
    Visit::create(['sales_id' => $this->sales->id, 'buyer_id' => $b->id, 'visited_at' => '2026-10-12 05:00']);
    Visit::create(['sales_id' => $this->sales->id, 'buyer_id' => $a->id, 'visited_at' => '2026-10-13 03:00']);
    Visit::create(['sales_id' => $this->sales->id, 'buyer_id' => $a->id, 'visited_at' => '2026-10-13 06:00']);

    $mie = Product::factory()->create(['stock' => 50, 'price' => 1000]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$mie->id => 10]);
    app(SalesNotes::class)->create($this->sales, BusinessLine::Naraya, $b->id, [$mie->id => 4], NotePaymentMethod::Cash); // 4.000, also a visit today

    CashDeposit::factory()->create(['sales_id' => $this->sales->id, 'status' => CashDepositStatus::Resolved, 'expected_amount' => 10000, 'claimed_amount' => 9000, 'resolved_at' => now()]);
    CashDeposit::factory()->create(['sales_id' => $this->sales->id, 'status' => CashDepositStatus::Submitted, 'expected_amount' => 5000, 'claimed_amount' => 5000]);

    $route = app(DeliveryRoutes::class)->start($this->sales, [$a->id]);
    app(DeliveryRoutes::class)->finish($route);
    app(DayOffs::class)->request($this->sales, '2026-10-16');
});

it('sums a sales person\'s month', function () {
    $p = app(SalesPerformance::class)->month($this->sales, Carbon::parse('2026-10-01'));

    expect($p)->toMatchArray([
        'kunjungan_toko' => 4,      // Oct 12 ×2, Oct 13 ×1, today ×1 (the note)
        'toko_unik' => 2,
        'rute_selesai' => 1,
        'nota' => 1,
        'penjualan' => 4000,
        'setoran' => 2,
        'setoran_terverifikasi' => 9000,
        'setoran_menunggu' => 1,
        'selisih_setoran' => 1000,
        'kewajiban' => 4000,        // the cash note is not deposited yet
        'libur' => 0,               // the day off is on Friday, after today
    ])->and($p['hari_kerja'])->toBe(14)
        ->and($p['toko_per_hari'])->toBe(round(4 / 14, 1));
});

it('shows the performance panel on the sales page and month columns in the list', function () {
    Livewire::actingAs($this->admin)->test(Form::class, ['sales' => $this->sales])
        ->assertSee('Performa')->assertSee('Rp 4.000')->assertSee('Kewajiban tunai')
        ->set('perfMonth', '2026-09')->assertSee('September 2026');

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSeeInOrder(['Libur', 'Toko/hari', 'Penjualan'])->assertSee('Rp 4.000');
});
