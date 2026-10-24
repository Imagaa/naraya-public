<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesProfile;
use App\Models\User;
use App\Support\Cart;

function headerBuyer(bool $withSales = true): User
{
    $area = Area::factory()->create();
    $salesId = null;

    if ($withSales) {
        $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Santoso']);
        SalesProfile::factory()->create(['user_id' => $sales->id, 'area_id' => $area->id, 'phone' => '0812-8841-2207']);
        $salesId = $sales->id;
    }

    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Toko Sumber Rejeki']);
    BuyerProfile::factory()->create([
        'user_id' => $buyer->id, 'area_id' => $area->id, 'sales_user_id' => $salesId,
        'address' => 'Jl. Gambir 5', 'loyalty_points' => 1240,
    ]);

    return $buyer;
}

it('shows the buyer\'s sales rep, address, points and cart total', function () {
    $buyer = headerBuyer();
    $product = Product::factory()->create(['price' => 10000, 'stock' => 10]);
    Cart::add($product->id, 3);

    $this->actingAs($buyer)->blade('<x-mp.header />')
        ->assertSee('Sales Anda')
        ->assertSee('Budi Santoso')
        ->assertSee('0812-8841-2207')
        ->assertSee('Toko Sumber Rejeki')
        ->assertSee('Jl. Gambir 5')
        ->assertSee('1.240 poin')
        ->assertSee('Rp 30.000');
});

it('hides the sales bar when the buyer has no sales rep', function () {
    $this->actingAs(headerBuyer(withSales: false))->blade('<x-mp.header />')
        ->assertDontSee('Sales Anda');
});

it('counts the buyer\'s orders being delivered', function () {
    $buyer = headerBuyer();
    Order::factory()->count(2)->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Shipped]);
    Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Completed]);

    $this->actingAs($buyer)->blade('<x-mp.header />')->assertSee('2 sedang dikirim');
});

it('shows a login button and no buyer data to guests', function () {
    $this->blade('<x-mp.header />')
        ->assertSee('Masuk')
        ->assertDontSee('Sales Anda')
        ->assertDontSee('Tukar hadiah')
        ->assertDontSee('sedang dikirim');
});

it('submits search to the product list', function () {
    $this->blade('<x-mp.header />')
        ->assertSee('action="'.route('products.index').'"', false)
        ->assertSee('name="q"', false);
});

it('can hide the mobile tab bar', function () {
    $this->blade('<x-mp.header />')->assertSee('class="tabbar"', false);
    $this->blade('<x-mp.header :tabbar="false" />')->assertDontSee('class="tabbar"', false);
});

it('renders the footer', function () {
    $this->blade('<x-mp.footer />')->assertSee('Naraya')->assertSee('QRIS');
});
