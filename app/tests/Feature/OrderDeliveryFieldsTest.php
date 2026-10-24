<?php

use App\Models\Order;

it('links a child order to its parent', function () {
    $parent = Order::factory()->create();
    $child = Order::factory()->create(['parent_order_id' => $parent->id]);

    expect($child->parentOrder->is($parent))->toBeTrue();
    expect($parent->childOrders->first()->is($child))->toBeTrue();
});
