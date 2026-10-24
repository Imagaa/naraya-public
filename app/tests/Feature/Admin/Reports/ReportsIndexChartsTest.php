<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Admin\Reports\Index;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->admin = User::factory()->create(['role' => Role::Admin]));

it('defaults the sales trend chart to the last 30 days, bucketed daily', function () {
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now(), 'total' => 10000]);
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now()->subDays(45), 'total' => 99999]); // outside the default range

    $view = Livewire::actingAs($this->admin)->test(Index::class);
    $trend = $view->viewData('salesTrend');

    expect($trend)->toHaveCount(30);
    expect(collect($trend)->sum('total'))->toBe(10000.0);
});

it('buckets a long custom range into weeks instead of noisy daily points', function () {
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now(), 'total' => 10000]);

    $view = Livewire::actingAs($this->admin)->test(Index::class)
        ->set('chartStart', now()->subDays(59)->toDateString())
        ->set('chartEnd', now()->toDateString())
        ->call('applyChartRange');

    // 60-day range should collapse into ~9 weekly points, not 60 daily ones.
    expect(count($view->viewData('salesTrend')))->toBeLessThan(15);
});

it('applies a quick range preset and dispatches updated chart data', function () {
    Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now(), 'total' => 10000]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('setChartRangePreset', 7)
        ->assertDispatched('dashboard-charts-updated');
});

it('rejects a custom range where the end date is before the start date', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('chartStart', now()->toDateString())
        ->set('chartEnd', now()->subDay()->toDateString())
        ->call('applyChartRange')
        ->assertHasErrors('chartEnd');
});

it('lists the top 5 best-selling products by quantity for the chart range', function () {
    $popular = Product::factory()->create();
    $unpopular = Product::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Completed, 'completed_at' => now()]);
    $order->items()->create(['product_id' => $popular->id, 'product_name' => 'Populer', 'price' => 1000, 'quantity' => 20, 'subtotal' => 20000]);
    $order->items()->create(['product_id' => $unpopular->id, 'product_name' => 'Kurang Laku', 'price' => 1000, 'quantity' => 2, 'subtotal' => 2000]);

    $view = Livewire::actingAs($this->admin)->test(Index::class);
    $top = $view->viewData('topProducts');

    expect($top[0])->toBe(['label' => 'Populer', 'total' => 20]);
});

it('shows the all-time order pipeline with counts and percentages', function () {
    Order::factory()->create(['status' => OrderStatus::New]);
    Order::factory()->create(['status' => OrderStatus::Completed]);
    Order::factory()->create(['status' => OrderStatus::Completed]);

    $view = Livewire::actingAs($this->admin)->test(Index::class);
    $pipeline = collect($view->viewData('orderPipeline'))->keyBy('label');

    expect($pipeline['Baru']['total'])->toBe(1);
    expect($pipeline['Selesai']['total'])->toBe(2);
    expect($pipeline['Selesai']['percent'])->toBe(67); // 2 of 3 total, rounded
});

it('still lists every report link under the charts', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('Grafik')
        ->assertSee('id="sales-trend-chart"', false)
        ->assertSee(route('admin.reports.penjualan'), false);
});
