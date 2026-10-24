<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Admin\Reports\TargetSales;
use App\Models\Order;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('computes realisasi per sales from completed orders in the period', function () {
    $salesUser = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $salesUser->id, 'is_active' => true, 'monthly_target' => 1000000]);

    Order::factory()->create([
        'sales_id' => $salesUser->id, 'status' => OrderStatus::Completed,
        'completed_at' => '2026-09-15', 'total' => 300000,
    ]);
    Order::factory()->create([
        'sales_id' => $salesUser->id, 'status' => OrderStatus::Completed,
        'completed_at' => '2026-08-15', 'total' => 999999,
    ]);

    $rows = Livewire::actingAs($this->admin)
        ->test(TargetSales::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->instance()
        ->rows();

    $row = collect($rows)->first();
    expect($row)->toContain('Rp 1.000.000');
    expect($row)->toContain('Rp 300.000');
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/target-sales')
        ->assertStatus(403);
});
