<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Marketplace\Orders\Show;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('shows the order to its own buyer', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'total' => 15000]);

    Livewire::actingAs($buyer)
        ->test(Show::class, ['order' => $order])
        ->assertSee('Pesanan #'.$order->id)
        ->assertSee('15.000');
});

it('blocks viewing another buyer\'s order', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $otherBuyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create(['buyer_id' => $otherBuyer->id]);

    Livewire::actingAs($buyer)
        ->test(Show::class, ['order' => $order])
        ->assertForbidden();
});

it('redirects guests to login', function () {
    $order = Order::factory()->create();

    $this->get('http://'.config('app.domain')."/pesanan/{$order->id}")
        ->assertRedirect(route('login'));
});

it('lets the buyer confirm receipt once Sales has confirmed delivery', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create([
        'buyer_id' => $buyer->id, 'status' => OrderStatus::Shipped, 'sales_delivered_at' => now(),
    ]);

    Livewire::actingAs($buyer)->test(Show::class, ['order' => $order])->call('confirmReceived');

    expect($order->fresh()->buyer_confirmed_at)->not->toBeNull();
});

it('blocks the buyer from confirming receipt before Sales has confirmed delivery', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create([
        'buyer_id' => $buyer->id, 'status' => OrderStatus::Shipped, 'sales_delivered_at' => null,
    ]);

    Livewire::actingAs($buyer)
        ->test(Show::class, ['order' => $order])
        ->call('confirmReceived')
        ->assertForbidden();

    expect($order->fresh()->buyer_confirmed_at)->toBeNull();
});
