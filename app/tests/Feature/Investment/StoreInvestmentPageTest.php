<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Investments\Index;
use App\Models\BuyerProfile;
use App\Models\InvestmentPurchase;
use App\Models\User;
use App\Support\Investment\Investments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-09 03:00'));
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id]);
});

it('shows the calculator card on the marketplace home until the store has bought, then this month\'s income', function () {
    $this->actingAs($this->store)->get('http://naraya.test/')
        ->assertSee('Uang toko ikut bekerja')->assertSee('Mulai investasi')->assertSee('11 kali mulai bulan ke-2')->assertDontSee('Pendapatan investasi bulan ini');

    $admin = User::factory()->create(['role' => Role::Admin]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    BuyerProfile::where('user_id', $this->store->id)->update(['sales_user_id' => $sales->id]);
    $p = app(Investments::class)->buyViaSales($sales, $this->store->id, 10);
    app(Investments::class)->activate($p, $admin);
    $this->travelTo(Carbon::parse('2026-12-01 03:00')); // a first purchase pays from month 2

    $this->actingAs($this->store)->get('http://naraya.test/')
        ->assertSee('Pendapatan investasi bulan ini')->assertSee('Rp280.000')->assertSee('10 paket aktif');
});

it('buys in steps — quantity, payment, proof, then the terms — with a transfer proof', function () {
    Livewire::actingAs($this->store)->test(Index::class)
        ->assertSee('Jumlah paket')->assertDontSee('Investasi Naraya, singkatnya')
        ->call('changeQty', 2)->call('nextStep')->assertSet('buyStep', 2)
        ->assertSee('BNI 0000000000')->assertDontSee('QRIS')
        ->set('method', 'wallet')->call('nextStep')->assertHasErrors('method')->assertSet('buyStep', 2) // transfer only since 2026-10-12
        ->set('method', 'transfer')->call('nextStep')
        ->call('nextStep')->assertHasErrors('proof')->assertSet('buyStep', 3)
        ->set('proof', UploadedFile::fake()->image('bukti.jpg'))->call('nextStep')->assertSet('buyStep', 4)
        ->assertSee('Investasi Naraya, singkatnya')
        ->call('buy')->assertHasErrors('agree')
        ->set('agree', true)->call('buy')->assertHasNoErrors()
        ->assertSet('buyStep', 1)->assertSee('Menunggu verifikasi');

    expect(InvestmentPurchase::sole())->quantity->toBe(3)->amount->toBe(2100000)->source->toBe('naraya');
});

it('withdraws from the wallet with the bank details', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    BuyerProfile::where('user_id', $this->store->id)->update(['sales_user_id' => $sales->id]);
    $p = app(Investments::class)->buyViaSales($sales, $this->store->id, 10);
    app(Investments::class)->activate($p, $admin);
    $this->travelTo(Carbon::parse('2026-12-10 03:00'));
    app(Investments::class)->payDue();

    Livewire::actingAs($this->store)->test(Index::class)
        ->assertSee('Rp280.000')
        ->set('amount', 100000)->set('bank', 'BCA')->set('accountNumber', '1234567')->set('accountName', 'Budi Santoso')
        ->call('withdraw')->assertHasNoErrors()
        ->assertSee('Rp180.000')->assertSee('Menunggu transfer');
});

it('keeps the page to logged-in stores', function () {
    $this->get('http://naraya.test/investasi')->assertRedirect();
});
