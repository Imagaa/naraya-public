<?php

use App\Enums\CashCollectionStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Admin\Dashboard\Index;
use App\Models\CashCollection;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->freezeTime();
});

it('shows the work queues grouped by job, each linking to its filtered list', function () {
    Order::factory()->create(['status' => OrderStatus::New, 'created_at' => now()->subHours(13)]);
    Product::factory()->create(['is_active' => true, 'stock' => 0, 'min_stock' => 5]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertViewHas('pending', 2)
        ->assertViewHas('lateQueues', 2)
        ->assertViewHas('groups', fn ($groups) => array_keys($groups) === ['Penjualan', 'Gudang'])
        ->assertSee('Order baru belum dikonfirmasi')
        ->assertSee(route('admin.orders.index', ['antrean' => 'new-orders']), false)
        ->assertSee('2 hal perlu ditangani')
        ->assertDontSee('Semua beres');
});

it('shows the all-clear state when nothing is waiting', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertViewHas('pending', 0)
        ->assertSee('Semua beres')
        ->assertSee('tidak ada yang menunggu')
        ->assertDontSee('Perlu ditangani');
});

it('computes the four KPIs from completed orders, in-flight orders and held cash', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'is_active' => true, 'monthly_target' => 400000]);
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now(), 'total' => 100000]);
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now()->subDay(), 'total' => 30000]);
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now()->subMonths(2), 'total' => 999999]);
    Order::factory()->create(['status' => OrderStatus::New]);
    Order::factory()->create(['status' => OrderStatus::Shipped]);
    Order::factory()->create(['status' => OrderStatus::Cancelled]);
    // Each collection belongs to an old completed order, so the factory doesn't add New orders.
    $cash = fn (array $attrs) => CashCollection::factory()->create($attrs + [
        'sales_id' => $sales->id,
        'order_id' => Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now()->subMonths(3)])->id,
    ]);
    $cash(['status' => CashCollectionStatus::Held, 'amount' => 15000]);
    $cash(['status' => CashCollectionStatus::Held, 'amount' => 5000]);
    $cash(['status' => CashCollectionStatus::Deposited, 'amount' => 99999]);

    $month = now()->day === 1 ? 100000 : 130000; // yesterday is last month on the 1st

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertViewHas('salesToday', 100000)
        ->assertViewHas('salesYesterday', 30000)
        ->assertViewHas('ordersInFlight', 2)
        ->assertViewHas('cashHeld', 20000)
        ->assertViewHas('salesHoldingCash', 1)
        ->assertViewHas('salesMonth', $month)
        ->assertViewHas('targetTotal', 400000)
        ->assertViewHas('targetPercent', (int) round($month / 400000 * 100));
});

it('shows 0% of target when no target is set', function () {
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now(), 'total' => 50000]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertViewHas('targetTotal', 0)
        ->assertViewHas('targetPercent', 0);
});

it('formats KPI money short', function () {
    $dashboard = new Index;

    expect($dashboard->rupiah(12_400_000))->toBe('Rp 12,4 jt')
        ->and($dashboard->rupiah(12_000_000))->toBe('Rp 12 jt')
        ->and($dashboard->rupiah(1_250_000_000))->toBe('Rp 1,3 M')
        ->and($dashboard->rupiah(950_000))->toBe('Rp 950.000');
});

it('refreshes itself every minute', function () {
    Livewire::actingAs($this->admin)->test(Index::class)->assertSeeHtml('wire:poll.60s');
});

it('blocks non-admin roles from the dashboard', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/')
        ->assertStatus(403);
});
