<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\DeliveryFlowSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    (new AreaSeeder())->run();
    (new UserSeeder())->run();
    User::factory()->create(['role' => Role::Admin]); // stock movement actor for ProductSeeder
    (new ProductSeeder())->run();
});

it('seeds one order per stage of the sales delivery pipeline', function () {
    (new DeliveryFlowSeeder())->run();

    $sales = User::where('email', 'sales1@naraya.test')->firstOrFail();
    $orders = Order::where('sales_id', $sales->id)->orderBy('id')->get();

    expect($orders)->toHaveCount(5);

    // #1 awaiting Gudang handover
    expect($orders[0]->status)->toBe(OrderStatus::Processing);
    expect($orders[0]->gudang_handover_at)->toBeNull();

    // #2 ready for Sales to confirm receipt
    expect($orders[1]->gudang_handover_at)->not->toBeNull();
    expect($orders[1]->sales_received_at)->toBeNull();

    // #3 received, ready to depart
    expect($orders[2]->sales_received_at)->not->toBeNull();
    expect($orders[2]->status)->toBe(OrderStatus::Processing);

    // #4 en route, with a destination pin for the route map / navigation handoff
    expect($orders[3]->status)->toBe(OrderStatus::Shipped);
    expect($orders[3]->delivery_lat)->not->toBeNull();
    expect($orders[3]->delivery_lng)->not->toBeNull();
    expect($orders[3]->sales_delivered_at)->toBeNull();

    // #5 delivered, awaiting buyer confirmation
    expect($orders[4]->sales_delivered_at)->not->toBeNull();
    expect($orders[4]->buyer_confirmed_at)->toBeNull();
});
