<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Livewire\Marketplace\Home;
use App\Models\BuyerProfile;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Cart;
use App\Support\ProductSearch;
use Livewire\Livewire;

beforeEach(function () {
    $food = Category::factory()->create(['name' => 'Mie Instan']);
    $toys = Category::factory()->create(['name' => 'Mobil & Kendaraan', 'line' => BusinessLine::Toys]);
    $this->mie = Product::factory()->create(['name' => 'Mie Goreng Spesial', 'category_id' => $food->id]);
    $this->toy = Product::factory()->create(['name' => 'Mobil-mobilan Plastik', 'category_id' => $toys->id, 'line' => BusinessLine::Toys]);
});

it('switches the storefront between Naraya and Naraya Toys', function () {
    $this->get('http://naraya.test/')->assertSee('Mie Instan')->assertDontSee('Mobil &amp; Kendaraan', false);

    $this->post('http://naraya.test/lini', ['line' => 'toys'])->assertRedirect();
    $this->get('http://naraya.test/')->assertSee('Mobil &amp; Kendaraan', false)->assertDontSee('Mie Instan');
    $this->get('http://naraya.test/produk')->assertSee('Mobil-mobilan Plastik')->assertDontSee('Mie Goreng Spesial');
    expect(array_keys(ProductSearch::rank('mobil')))->toContain($this->toy->id);
});

it('follows a product link into its own line', function () {
    $this->get('http://naraya.test/produk/'.$this->toy->slug)->assertOk()->assertSee('Mobil-mobilan Plastik');
    expect(ActiveLine::current())->toBe(BusinessLine::Toys);
});

it('blocks buying with the on-hold wording while purchasing is closed', function () {
    config(['naraya.marketplace_purchasing_open' => false]);
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id]);

    $this->actingAs($store)->get('http://naraya.test/')
        ->assertSee('Pemesanan online ditutup sementara')
        ->assertDontSee('ke keranjang');

    Livewire::actingAs($store)->test(Home::class)->call('addToCart', $this->mie->id);
    expect(Cart::count())->toBe(0);

    $this->actingAs($store)->get('http://naraya.test/checkout')->assertOk()->assertSee('Pemesanan online ditutup sementara');
});
