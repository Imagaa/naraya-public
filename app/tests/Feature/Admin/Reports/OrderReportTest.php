<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Admin\Reports\OrderReport;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists orders of any status within the date range', function () {
    $inRange = Order::factory()->create(['status' => OrderStatus::New, 'created_at' => '2026-09-15']);
    $outOfRange = Order::factory()->create(['status' => OrderStatus::New, 'created_at' => '2026-08-15']);

    $rows = Livewire::actingAs($this->admin)
        ->test(OrderReport::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->instance()
        ->rows();

    expect($rows)->toHaveCount(1);
    expect(collect($rows)->flatten()->implode(','))->toContain('#'.$inRange->id);
    expect(collect($rows)->flatten()->implode(','))->not->toContain('#'.$outOfRange->id);
});

it('exports the report to pdf', function () {
    Order::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(OrderReport::class)
        ->call('exportPdf')
        ->assertStatus(200);
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/order')
        ->assertStatus(403);
});
