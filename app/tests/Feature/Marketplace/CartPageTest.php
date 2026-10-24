<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Checkout;
use App\Models\BuyerProfile;
use App\Models\LoyaltySetting;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Livewire\Livewire;

beforeEach(function () {
    LoyaltySetting::first()?->update(['rupiah_per_point' => 10000]) ?? LoyaltySetting::create(['rupiah_per_point' => 10000]);
    $this->buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $this->buyer->id, 'credit_balance' => 25000]);
    $this->actingAs($this->buyer);
});

it('renders the cart in the marketplace layout with savings and a points estimate', function () {
    $promo = Product::factory()->create(['price' => 66000, 'discount_price' => 52800, 'stock' => 50, 'is_active' => true]);
    $plain = Product::factory()->create(['price' => 204000, 'discount_price' => null, 'stock' => 50, 'is_active' => true]);
    Cart::add($promo->id, 3);
    Cart::add($plain->id, 1);

    // gross 402.000, net 362.400, savings 39.600, minus 25.000 credit = 337.400; orders earn no points
    $this->get(route('checkout'))
        ->assertOk()
        ->assertSee('<body class="mp" data-line="', false)
        ->assertSee('class="taskbar"', false)
        ->assertSee('Hemat dari promo')
        ->assertSee('Rp 39.600')
        ->assertSee('Rp 337.400')
        ->assertDontSee('poin setelah pesanan selesai')
        ->assertDontSee('<table', false);
});

it('hides savings and points when nothing is discounted and credit covers the order', function () {
    $this->buyer->buyerProfile->update(['credit_balance' => 999999]);
    $plain = Product::factory()->create(['price' => 50000, 'discount_price' => null, 'stock' => 5, 'is_active' => true]);
    Cart::add($plain->id, 1);

    Livewire::test(Checkout::class)
        ->assertDontSee('Hemat dari promo')
        ->assertDontSee('poin setelah pesanan selesai')
        ->assertViewHas('grandTotal', 0);
});

it('recommends products that are not already in the cart', function () {
    $inCart = Product::factory()->create(['stock' => 10, 'is_active' => true, 'name' => 'Sudah Di Keranjang']);
    Product::factory()->create(['stock' => 10, 'is_active' => true, 'name' => 'Saran Lain']);
    Cart::add($inCart->id, 1);

    Livewire::test(Checkout::class)
        ->assertSee('Lengkapi belanja Anda')
        ->assertSee('Saran Lain')
        ->assertViewHas('recommendations', fn ($r) => ! $r->pluck('id')->contains($inCart->id));
});

it('empties the cart', function () {
    $p = Product::factory()->create(['stock' => 10, 'is_active' => true]);
    Cart::add($p->id, 2);

    Livewire::test(Checkout::class)->call('clearCart');

    expect(Cart::count())->toBe(0);
});

it('uses Lucide icons for payment methods and a native dialog for cash', function () {
    $p = Product::factory()->create(['stock' => 10, 'is_active' => true]);
    Cart::add($p->id, 1);

    Livewire::test(Checkout::class)
        ->set('step', 'payment')
        ->assertSee('Tunai saat barang tiba')
        ->assertSee('<dialog', false)
        ->assertDontSee('💵')
        ->assertDontSee('🔳')
        ->assertDontSee('📱');
});

it('shows an empty cart with a way back to shopping', function () {
    Livewire::test(Checkout::class)
        ->assertSee('Keranjang masih kosong')
        ->assertSee('Belanja Sekarang');
});

it('shows the reorder message carried over from Pesan Lagi', function () {
    session()->flash('status', '2 produk ditambahkan ke keranjang.');

    $this->get(route('checkout'))->assertSee('2 produk ditambahkan ke keranjang.');
});

it('loads the map bundle on checkout but not on the home page', function () {
    $this->get(route('checkout'))->assertSee('pinpoint-map', false);
    $this->get('/')->assertDontSee('pinpoint-map', false);
});

it('offers a rail to complete the cart, and best sellers when it is empty', function () {
    Product::factory()->count(8)->create(['stock' => 50]);
    $this->get(route('checkout'))->assertSee('Terlaris')->assertDontSee('Lengkapi belanja Anda');

    Cart::add(Product::first()->id, 2);
    $this->get(route('checkout'))->assertSee('Lengkapi belanja Anda');
});
