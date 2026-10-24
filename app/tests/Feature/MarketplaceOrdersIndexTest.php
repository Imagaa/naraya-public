<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Marketplace\Orders;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Livewire\Livewire;

it('shows only the logged-in buyer\'s own orders', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $otherBuyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'total' => 15000]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Aqua Botol']);
    Order::factory()->create(['buyer_id' => $otherBuyer->id]);

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->assertSee('Pesanan #'.$order->id)
        ->assertSee('Aqua Botol')
        ->assertSee('15.000');
});

it('shows an empty state when the buyer has no orders', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->assertSee('Belum ada pesanan.');
});

it('redirects guests to login', function () {
    $this->get('http://'.config('app.domain').'/pesanan')
        ->assertRedirect(route('login'));
});

it('filters orders by status', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $newOrder = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::New]);
    $completedOrder = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Completed]);

    $test = Livewire::actingAs($buyer)->test(Orders::class);

    $test->assertSee('Pesanan #'.$newOrder->id)->assertSee('Pesanan #'.$completedOrder->id);

    $test->call('setFilter', 'completed')
        ->assertDontSee('Pesanan #'.$newOrder->id)
        ->assertSee('Pesanan #'.$completedOrder->id);

    $test->call('setFilter', 'new')
        ->assertSee('Pesanan #'.$newOrder->id)
        ->assertDontSee('Pesanan #'.$completedOrder->id);
});

it('shows a status-specific empty state when a filter has no matching orders', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::New]);

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->call('setFilter', 'completed')
        ->assertSee('Belum ada pesanan dengan status ini.');
});
