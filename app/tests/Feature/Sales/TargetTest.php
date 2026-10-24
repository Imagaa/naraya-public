<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Sales\Target\Index;
use App\Models\Order;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

it('shows this month\'s realized total against the monthly target', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->for($sales)->create(['monthly_target' => 5000000]);

    Order::factory()->create([
        'sales_id' => $sales->id,
        'status' => OrderStatus::Completed,
        'total' => 1250000,
        'completed_at' => now(),
    ]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->assertViewHas('target', 5000000)
        ->assertViewHas('realized', 1250000);
});

it('excludes another sales user\'s orders from the realized total', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);

    Order::factory()->create([
        'sales_id' => $otherSales->id,
        'status' => OrderStatus::Completed,
        'total' => 9999999,
        'completed_at' => now(),
    ]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->assertViewHas('realized', 0);
});
