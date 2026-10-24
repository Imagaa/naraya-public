<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Admin\Reports\Toko;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use App\Support\ActiveLine;
use App\Support\Sales\SalesNotes;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// Laporan Toko
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 03:00'));
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->toy = Product::factory()->create(['name' => 'Kartu Mainan', 'line' => BusinessLine::Toys, 'price' => 1000, 'unit' => 'pcs', 'pieces_per_unit' => 1]);
    session([ActiveLine::KEY => BusinessLine::Toys->value]);
    $this->store = function (string $name, int $pcs = 0, ?string $visit = null, bool $account = false) {
        $user = User::factory()->create(['role' => Role::User, 'name' => $name, 'password' => $account ? 'rahasia123' : null]);
        BuyerProfile::factory()->create(['user_id' => $user->id, 'sales_user_id' => $this->sales->id]);
        if ($visit) {
            Visit::create(['sales_id' => $this->sales->id, 'buyer_id' => $user->id, 'visited_at' => $visit]);
        }
        if ($pcs) {
            app(SalesNotes::class)->record($this->sales, BusinessLine::Toys, $user->id, [$this->toy->id => $pcs], NotePaymentMethod::Cash, null, null, 'bawaan');
        }

        return $user;
    };
});

it('shows totals, store counts, items and the ranking', function () {
    ($this->store)('Toko Anggrek', 30, account: true);
    ($this->store)('Toko Bougenvil');

    Livewire::actingAs($this->admin)->test(Toko::class)
        ->assertSee('Laporan Toko')
        ->assertSee('30 pcs')->assertSee('Rp 30.000')
        ->assertSee('1 dari 2')                       // toko dengan penjualan
        ->assertSee('sudah punya akun · 1 belum')     // akun toko
        ->assertSee('Kartu Mainan')->assertSee('Total')
        ->assertSeeInOrder(['Toko Anggrek', 'Toko Bougenvil'])
        ->assertSee(route('admin.buyers.show', User::where('name', 'Toko Anggrek')->first()), false);
});

it('sorts by each option', function () {
    ($this->store)('Toko Sedikit', 5, '2026-10-22 03:00');
    ($this->store)('Toko Banyak', 50);
    ($this->store)('Toko Lama', 1, '2026-09-01 03:00');
    Visit::where('buyer_id', User::where('name', 'Toko Lama')->value('id'))->where('visited_at', '>', '2026-10-01')->update(['visited_at' => '2026-09-02 03:00']);

    Livewire::actingAs($this->admin)->test(Toko::class)
        ->assertSeeInOrder(['Toko Banyak', 'Toko Sedikit', 'Toko Lama'])
        ->set('sort', 'lama')
        ->assertSeeInOrder(['Toko Lama', 'Toko Banyak', 'Toko Sedikit'])
        ->set('sort', 'terjual')
        ->assertSeeInOrder(['Toko Banyak', 'Toko Sedikit', 'Toko Lama']);
});

it('paginates 15 stores per page', function () {
    foreach (range(1, 16) as $i) {
        ($this->store)(sprintf('Toko %02d', $i), 17 - $i);   // Toko 01 sells most
    }

    Livewire::actingAs($this->admin)->test(Toko::class)
        ->assertSee('Toko 15')->assertDontSee('Toko 16')
        ->call('gotoPage', 2)
        ->assertSee('Toko 16')->assertDontSee('Toko 15');
});

it('is reachable from the Toko list and the Laporan index', function () {
    $admin = 'http://admin.'.config('app.domain');
    $this->actingAs($this->admin)->get($admin.'/buyers')->assertSee('Rekap semua toko')->assertSee(route('admin.reports.toko'), false);
    $this->actingAs($this->admin)->get($admin.'/laporan')->assertSee(route('admin.reports.toko'), false);
    $this->actingAs($this->admin)->get($admin.'/laporan/toko')->assertOk();
    $this->actingAs($this->sales)->get($admin.'/laporan/toko')->assertForbidden();
});
