<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Marketplace\Checkout;
use App\Models\Address;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('redirects guests to login', function () {
    $this->get('http://'.config('app.domain').'/checkout')
        ->assertRedirect(route('login'));
});

it('creates an order from the cart with snapshot prices and clears the cart', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    Address::factory()->for($buyer)->create();
    $product = Product::factory()->create(['price' => 5000, 'stock' => 10]);
    Cart::add($product->id, 3);

    $test = Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->call('placeOrder');

    $order = Order::where('buyer_id', $buyer->id)->firstOrFail();
    $test->assertRedirect(route('orders.show', $order));
    expect($order->status)->toBe(OrderStatus::New);
    expect($order->total)->toBe(15000);
    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->price)->toBe(5000);
    expect($order->delivery_province)->toBe('Jawa Tengah');
    expect(Cart::items())->toBeEmpty();
});

it('rejects checkout with an empty cart', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    Address::factory()->for($buyer)->create();

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->call('placeOrder')
        ->assertHasErrors('cart');

    expect(Order::count())->toBe(0);
});

it('applies buyer credit balance to reduce the order total and deducts it from the balance', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    Address::factory()->for($buyer)->create();
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'credit_balance' => 3000]);
    $product = Product::factory()->create(['price' => 5000, 'stock' => 10]);
    Cart::add($product->id, 2); // subtotal 10000

    $test = Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->call('placeOrder');

    $order = Order::where('buyer_id', $buyer->id)->firstOrFail();
    $test->assertRedirect(route('orders.show', $order));
    expect($order->subtotal)->toBe(10000);
    expect($order->credit_applied)->toBe(3000);
    expect($order->total)->toBe(7000);
    expect($buyer->buyerProfile->fresh()->credit_balance)->toBe(0);
});

it('keeps unused credit balance when it exceeds the order total', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    Address::factory()->for($buyer)->create();
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'credit_balance' => 50000]);
    $product = Product::factory()->create(['price' => 5000, 'stock' => 10]);
    Cart::add($product->id, 1); // subtotal 5000

    $test = Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->call('placeOrder');

    $order = Order::where('buyer_id', $buyer->id)->firstOrFail();
    $test->assertRedirect(route('orders.show', $order));
    expect($order->credit_applied)->toBe(5000);
    expect($order->total)->toBe(0);
    expect($buyer->buyerProfile->fresh()->credit_balance)->toBe(45000);
});

it('auto-selects the buyer default address on mount', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $address = Address::factory()->for($buyer)->create(['is_default' => true]);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSet('selectedAddressId', $address->id)
        ->assertSet('addingNewAddress', false);
});

it('shows the add-address form when the buyer has no saved address', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSet('selectedAddressId', null)
        ->assertSet('addingNewAddress', true);
});

it('saves a new address to the address book and marks it default when placing an order', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $product = Product::factory()->create(['price' => 5000, 'stock' => 10]);
    Cart::add($product->id, 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->set('newProvinceId', '33')
        ->set('newProvinceName', 'Jawa Tengah')
        ->set('newCityId', '3374')
        ->set('newCityName', 'Kota Semarang')
        ->set('newDistrictId', '3374010')
        ->set('newDistrictName', 'Semarang Tengah')
        ->set('newVillageId', '3374010001')
        ->set('newVillageName', 'Bumikasi')
        ->set('newDetail', 'Jl. Contoh No. 1')
        ->set('newLat', -6.9667)
        ->set('newLng', 110.4167)
        ->call('goToPayment')
        ->call('placeOrder');

    $address = $buyer->addresses()->sole();
    expect($address->is_default)->toBeTrue();

    $order = Order::where('buyer_id', $buyer->id)->firstOrFail();
    expect($order->delivery_province)->toBe('Jawa Tengah');
    expect($order->delivery_address)->toContain('Jl. Contoh No. 1');
    expect($order->delivery_lat)->toBe(-6.9667);
    expect($order->delivery_lng)->toBe(110.4167);
});

it('requires a full new-address selection before continuing to payment', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $product = Product::factory()->create(['price' => 5000, 'stock' => 10]);
    Cart::add($product->id, 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->call('goToPayment')
        ->assertHasErrors(['newProvinceId', 'newCityId', 'newDistrictId', 'newVillageId', 'newDetail', 'newLat', 'newLng']);

    expect(Order::count())->toBe(0);
});
