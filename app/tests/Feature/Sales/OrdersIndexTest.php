<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Sales\Orders\Index;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('shows only orders assigned to the logged-in sales user', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);

    $mine = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Processing]);
    Order::factory()->create(['sales_id' => $otherSales->id, 'status' => OrderStatus::Processing]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->assertSee('#'.$mine->id);
});

it('does not show orders not yet assigned to any sales', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $unassigned = Order::factory()->create(['sales_id' => null, 'status' => OrderStatus::Processing]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->assertDontSee('#'.$unassigned->id);
});

it('groups orders by delivery stage, most urgent first, with a count per group', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    Order::factory()->count(2)->create(['sales_id' => $sales->id, 'status' => OrderStatus::Processing]);
    $readyToReceive = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Processing, 'gudang_handover_at' => now()]);
    Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped]);
    Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'sales_delivered_at' => now()]);

    $component = Livewire::actingAs($sales)->test(Index::class)
        ->assertSeeInOrder(['Perlu Diambil (1)', 'Dalam Perjalanan (1)', 'Menunggu Gudang (2)', 'Terkirim · Menunggu Konfirmasi (1)'])
        ->assertDontSee('Siap Kirim (');

    expect($component->viewData('groups')->keys()->all())
        ->toBe(['ready_to_receive', 'in_transit', 'awaiting_handover', 'awaiting_buyer_confirm'])
        ->and($component->viewData('groups')['ready_to_receive']->pluck('id')->all())->toBe([$readyToReceive->id]);
});

it('hides the nav badge when there is nothing left to act on', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'sales_delivered_at' => now()]);

    $this->actingAs($sales)
        ->get('http://sales.'.config('app.domain').'/pesanan')
        ->assertDontSee('nav-badge');
});
