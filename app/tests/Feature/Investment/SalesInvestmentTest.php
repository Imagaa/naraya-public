<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Livewire\Admin\Deposits\Show as AdminDeposit;
use App\Livewire\Sales\Deposits\Index as SalesDeposits;
use App\Livewire\Sales\Investments\Index;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\InvestmentPurchase;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Sales\Reconciliation;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-09 03:00'));
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
});

it('records a cash investment for an assigned store from the sales app', function () {
    Livewire::actingAs($this->sales)->test(Index::class)
        ->assertSee('Toko Maju')
        ->set('storeId', $this->store->id)->set('qty', 3)
        ->call('save')->assertHasErrors('received')
        ->set('received', true)->call('save')->assertHasNoErrors()
        ->assertSee('INV-')->assertSee('Rp2.100.000');

    expect(InvestmentPurchase::sole())->payment_method->toBe('cash_sales')->sales_id->toBe($this->sales->id)->quantity->toBe(3);
    $this->actingAs($this->sales)->get(salesUrl('/'))->assertSee('Investasi toko');
});

it('puts investment cash into the Naraya deposit, the cash card on Kerja and Pencocokan', function () {
    Livewire::actingAs($this->sales)->test(Index::class)->set('storeId', $this->store->id)->set('qty', 2)->set('received', true)->call('save');

    Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\Home::class)->assertViewHas('cashHeld', 1400000);
    expect(app(Reconciliation::class)->forSales($this->sales, BusinessLine::Naraya, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-09')))
        ->toMatchArray(['investCash' => 1400000, 'investCashOpen' => 1400000, 'owed' => 1400000]);

    ActiveLine::set(BusinessLine::Toys);
    Livewire::actingAs($this->sales)->test(SalesDeposits::class)->assertDontSee('Toko Maju');
    ActiveLine::set(BusinessLine::Naraya);
    Livewire::actingAs($this->sales)->test(SalesDeposits::class)
        ->assertSet('claimedAmount', 1400000)->assertSee('Investasi')->assertSee('Toko Maju')
        ->call('submitDeposit');

    $deposit = CashDeposit::sole();
    expect($deposit->expected_amount)->toBe(1400000)
        ->and(InvestmentPurchase::sole()->cash_deposit_id)->toBe($deposit->id);

    $admin = User::factory()->create(['role' => Role::Admin]);
    Livewire::actingAs($admin)->test(AdminDeposit::class, ['deposit' => $deposit])->assertSee(InvestmentPurchase::sole()->number());
});
