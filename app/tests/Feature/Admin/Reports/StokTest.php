<?php

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Livewire\Admin\Reports\Stok;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists stock movements within the date range', function () {
    $product = Product::factory()->create(['name' => 'Mie Instan']);
    $user = User::factory()->create(['role' => Role::Admin]);

    StockMovement::create([
        'product_id' => $product->id, 'type' => StockMovementType::In,
        'quantity' => 10, 'user_id' => $user->id, 'created_at' => '2026-09-15',
    ]);
    StockMovement::create([
        'product_id' => $product->id, 'type' => StockMovementType::In,
        'quantity' => 5, 'user_id' => $user->id, 'created_at' => '2026-08-15',
    ]);

    $rows = Livewire::actingAs($this->admin)
        ->test(Stok::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->instance()
        ->rows();

    expect($rows)->toHaveCount(1);
    expect(collect($rows)->flatten()->implode(','))->toContain('Mie Instan');
});

it('shows current out-of-stock and low-stock counts, not date-filtered', function () {
    Product::factory()->create(['is_active' => true, 'stock' => 0]);

    Livewire::actingAs($this->admin)
        ->test(Stok::class)
        ->assertSee('1');
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/stok')
        ->assertStatus(403);
});
