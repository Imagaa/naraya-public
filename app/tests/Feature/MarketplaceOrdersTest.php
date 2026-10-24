<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Orders;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('shows only the authenticated buyer\'s own orders', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $otherBuyer = User::factory()->create(['role' => Role::User]);

    Order::factory()->create(['buyer_id' => $buyer->id, 'total' => 15000]);
    Order::factory()->create(['buyer_id' => $otherBuyer->id, 'total' => 99000]);

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->assertSee('15.000')
        ->assertDontSee('99.000');
});

it('redirects guests to login', function () {
    $this->get('http://'.config('app.domain').'/pesanan')
        ->assertRedirect(route('login'));
});
