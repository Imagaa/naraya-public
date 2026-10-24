<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Account;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => Role::User, 'name' => 'Toko Sumber Rejeki']);
    BuyerProfile::factory()->create(['user_id' => $this->buyer->id, 'loyalty_points' => 1240, 'credit_balance' => 25000]);
    $this->actingAs($this->buyer);
});

it('shows the store card with points, credit and active orders', function () {
    Order::factory()->create(['buyer_id' => $this->buyer->id, 'status' => 'processing']);
    Order::factory()->create(['buyer_id' => $this->buyer->id, 'status' => 'shipped']);
    Order::factory()->create(['buyer_id' => $this->buyer->id, 'status' => 'completed']);

    $this->get(route('account'))
        ->assertOk()
        ->assertSee('<body class="mp" data-line="', false)
        ->assertSee('Toko Sumber Rejeki')
        ->assertSee('1.240')
        ->assertSee('0 / 150 pcs menuju poin berikutnya')
        ->assertSee('Rp 25.000')
        ->assertSee('pelanggan sejak')
        ->assertDontSee('<table', false);

    Livewire::test(Account::class)->assertViewHas('activeOrders', 2);
});

it('links every menu section to its own page instead of toggling a panel', function () {
    $this->get(route('account'))->assertOk()
        ->assertSeeHtml('href="'.route('account', 'profil').'"')
        ->assertSeeHtml('href="'.route('account', 'alamat').'"')
        ->assertSeeHtml('href="'.route('account', 'keamanan').'"')
        ->assertSeeHtml('class="two acct"') // the overview: master view on phones
        ->assertDontSee('switchTab');
});

it('opens a dedicated section page with a back link to the account overview', function (string $section, string $heading) {
    $html = $this->get(route('account', $section))->assertOk()
        ->assertSee($heading)
        ->assertSeeHtml('class="two acct is-detail"')
        ->assertSeeHtml('href="'.route('account').'" class="icon-btn" aria-label="Kembali"')
        ->getContent();

    expect(preg_match('#href="'.preg_quote(route('account', $section), '#').'"\s+aria-current="page"#', $html))->toBe(1);
})->with([
    ['profil', 'Profil'],
    ['alamat', 'Alamat pengiriman'],
    ['keamanan', 'Ubah Kata Sandi'],
]);

it('returns 404 for an unknown account section', function () {
    $this->get('http://'.config('app.domain').'/akun/pengaturan')->assertNotFound();
});

it('shows saved addresses as cards with actions', function () {
    \App\Models\Address::factory()->create(['user_id' => $this->buyer->id, 'label' => 'Toko', 'detail' => 'Jl. Kramat Raya 41']);

    Livewire::test(Account::class, ['section' => 'alamat'])
        ->assertSee('Jl. Kramat Raya 41')
        ->assertSee('Titik peta tersimpan')
        ->assertSee('Tambah alamat baru');
});
