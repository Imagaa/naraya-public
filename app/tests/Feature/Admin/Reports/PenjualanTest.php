<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Admin\Reports\Penjualan;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists completed orders within the date range and excludes ones outside it', function () {
    $inRange = Order::factory()->create([
        'status' => OrderStatus::Completed,
        'completed_at' => '2026-09-30 16:50:00', // 23:50 WIB, last minutes of 30 Sep
        'total' => 100000,
    ]);
    $outOfRange = Order::factory()->create([
        'status' => OrderStatus::Completed,
        'completed_at' => '2026-09-30 17:10:00', // 00:10 WIB, already 1 Oct
        'total' => 999999,
    ]);
    $notCompleted = Order::factory()->create([
        'status' => OrderStatus::New,
        'total' => 555555,
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(Penjualan::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30');

    $rows = $component->instance()->rows();

    expect($rows)->toHaveCount(1);
    expect(collect($rows)->flatten()->implode(','))->toContain('#'.$inRange->id);
    expect(collect($rows)->flatten()->implode(','))->not->toContain('#'.$outOfRange->id);
    expect(collect($rows)->flatten()->implode(','))->not->toContain('#'.$notCompleted->id);
});

it('exports the report to excel', function () {
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now()]);

    Livewire::actingAs($this->admin)
        ->test(Penjualan::class)
        ->call('exportExcel')
        ->assertStatus(200);
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/penjualan')
        ->assertStatus(403);
});
