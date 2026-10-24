<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Admin\Orders\Show;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('marks a pending refund as sent, by whom and when', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled, 'refund_status' => 'pending']);

    Livewire::actingAs($admin)->test(Show::class, ['order' => $order])->call('markRefunded');

    expect($order->fresh())->refund_status->toBe('done')->refunded_by->toBe($admin->id)->refunded_at->not->toBeNull();
});

it('refuses to mark a refund on an order that does not need one', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled, 'refund_status' => null]);

    Livewire::actingAs($admin)->test(Show::class, ['order' => $order])->call('markRefunded')->assertForbidden();
});
