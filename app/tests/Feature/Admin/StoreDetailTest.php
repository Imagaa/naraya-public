<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Admin\Buyers\Show;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Sales\SalesNotes;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// Store detail page
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 03:00')); // 10:00 WIB
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Santoso']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Anggrek', 'password' => null]);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id, 'owner_name' => 'Bu Ani']);
    $this->toy = Product::factory()->create(['name' => 'Kartu Mainan', 'line' => BusinessLine::Toys, 'price' => 1000, 'unit' => 'pcs', 'pieces_per_unit' => 1]);
    session([ActiveLine::KEY => BusinessLine::Toys->value]);
    $this->sell = function (string $at, int $pcs) {
        $this->travelTo(Carbon::parse($at));
        app(SalesNotes::class)->record($this->sales, BusinessLine::Toys, $this->store->id, [$this->toy->id => $pcs], NotePaymentMethod::Cash, null, null, 'bawaan');
        $this->travelTo(Carbon::parse('2026-10-23 03:00'));
    };
});

it('shows a store\'s recap for the period', function () {
    ($this->sell)('2026-10-22 03:00', 160);

    Livewire::actingAs($this->admin)->test(Show::class, ['buyer' => $this->store])
        ->assertSee('Toko Anggrek')->assertSee('Bu Ani')->assertSee('Budi Santoso')
        ->assertSee('Belum punya akun')
        ->assertSee('Poin tersimpan dan langsung bisa dipakai saat akun toko dibuat.')
        ->assertSee('10 / 150 pcs menuju poin berikutnya')
        ->assertSee('Rp 160.000')
        ->assertSee('Kartu Mainan')->assertSee('Total')
        ->assertSee('1–23 Okt 2026');
});

it('switches period from the query string', function () {
    ($this->sell)('2026-10-22 03:00', 7);   // yesterday
    ($this->sell)('2026-10-23 02:00', 3);   // today

    Livewire::withQueryParams(['period' => 'today'])->actingAs($this->admin)->test(Show::class, ['buyer' => $this->store])
        ->assertSet('period', 'today')
        ->assertSee('Rp 3.000')->assertDontSee('Rp 10.000');
});

it('renders an idle store', function () {
    Livewire::actingAs($this->admin)->test(Show::class, ['buyer' => $this->store])
        ->assertOk()->assertSee('Belum ada penjualan di periode ini.')->assertSee('Kunjungan terakhir —');
});

it('links the store name from the Toko list', function () {
    $this->actingAs($this->admin)->get('http://admin.'.config('app.domain').'/buyers')
        ->assertOk()->assertSee(route('admin.buyers.show', $this->store), false);
});

it('is admin only', function () {
    $this->actingAs($this->sales)->get('http://admin.'.config('app.domain').'/buyers/'.$this->store->id)->assertForbidden();
    $this->actingAs($this->admin)->get('http://admin.'.config('app.domain').'/buyers/'.$this->store->id)->assertOk();
});
