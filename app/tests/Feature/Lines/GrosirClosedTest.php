<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Livewire\Sales\Deposits\Index as SalesDeposits;
use App\Models\BuyerProfile;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Investment\Investments;
use App\Support\Sales\Reconciliation;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// Grosir (the Naraya line) is "sedang dikembangkan" on the marketplace and the sales app (2026-10-11).
beforeEach(function () {
    config(['naraya.grosir_open' => false]);
    $food = Category::factory()->create(['name' => 'Mie Instan']);
    $toys = Category::factory()->create(['name' => 'Mobil & Kendaraan', 'line' => BusinessLine::Toys]);
    $this->mie = Product::factory()->create(['name' => 'Mie Goreng Spesial', 'category_id' => $food->id]);
    $this->toy = Product::factory()->create(['name' => 'Mobil-mobilan Plastik', 'category_id' => $toys->id, 'line' => BusinessLine::Toys]);
});

it('opens the marketplace on Toys, shows grosir as a disabled tab, and never switches to it', function () {
    $this->get('http://naraya.test/')->assertSee('Mobil &amp; Kendaraan', false)->assertDontSee('Mie Instan')
        ->assertSee('Grosir, mainan anak, dan')
        ->assertSeeHtml('value="toys" aria-pressed="true"')->assertDontSeeHtml('value="naraya"')->assertSee('Sedang dikembangkan');

    $this->post('http://naraya.test/lini', ['line' => 'naraya'])->assertRedirect();
    $this->get('http://naraya.test/produk')->assertSee('Mobil-mobilan Plastik')->assertDontSee('Mie Goreng Spesial');
    $this->get('http://naraya.test/produk/'.$this->mie->slug)->assertNotFound();
    $this->get('http://naraya.test/produk/'.$this->toy->slug)->assertOk();
});

it('closes grosir in the sales app but not in admin', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($sales)->get(salesUrl('/'))
        ->assertSeeHtml('value="toys" aria-pressed="true"')->assertDontSeeHtml('value="naraya"')->assertSee('Sedang dikembangkan');
    $this->actingAs($admin)->get('http://'.config('app.admin_domain').'/')
        ->assertSeeHtml('value="naraya" aria-pressed="true"')->assertDontSee('Sedang dikembangkan');
});

it('puts investment cash on the Toys deposit and Pencocokan while grosir is closed', function () {
    $this->travelTo(Carbon::parse('2026-10-09 03:00'));
    $sales = User::factory()->create(['role' => Role::Sales]);
    $store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $sales->id]);
    app(Investments::class)->buyViaSales($sales, $store->id, 1);

    expect(app(Reconciliation::class)->forSales($sales, BusinessLine::Toys, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-09')))
        ->toMatchArray(['investCash' => 700000, 'investCashOpen' => 700000]);
    ActiveLine::set(BusinessLine::Toys);
    Livewire::actingAs($sales)->test(SalesDeposits::class)->assertSet('claimedAmount', 700000)->assertSee('Toko Maju');
});
