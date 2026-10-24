<?php

use App\Enums\Role;
use App\Livewire\Admin\Reports\Komplain;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists complaints within the date range', function () {
    $order = Order::factory()->create();

    Complaint::factory()->create(['order_id' => $order->id, 'created_at' => '2026-09-15']);
    Complaint::factory()->create(['order_id' => $order->id, 'created_at' => '2026-08-15']);

    $rows = Livewire::actingAs($this->admin)
        ->test(Komplain::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->instance()
        ->rows();

    expect($rows)->toHaveCount(1);
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/komplain')
        ->assertStatus(403);
});
