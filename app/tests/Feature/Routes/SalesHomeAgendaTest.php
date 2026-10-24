<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\User;
use App\Support\SalesTarget;
use App\Support\Sales\DeliveryRoutes;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;

beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->stores = collect(range(1, 2))->map(function () {
        $store = User::factory()->create(['role' => Role::User]);
        BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id]);

        return $store;
    });
    $mie = Product::factory()->create(['stock' => 50, 'price' => 2000]);
    $toy = Product::factory()->create(['stock' => 5, 'price' => 30000, 'line' => BusinessLine::Toys]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$mie->id => 10]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Toys, [$toy->id => 2]);
    $this->route = app(DeliveryRoutes::class)->start($this->sales, $this->stores->pluck('id')->all());
    $notes = app(SalesNotes::class);
    $notes->create($this->sales, BusinessLine::Naraya, $this->stores[0]->id, [$mie->id => 3], NotePaymentMethod::Cash, routeStopId: $this->route->stops[0]->id);
    $notes->create($this->sales, BusinessLine::Toys, $this->stores[1]->id, [$toy->id => 1], NotePaymentMethod::Cash);
});

it('shows the route progress, the carried stock and undeposited cash from notes on Kerja', function () {
    \Livewire\Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\Home::class)
        ->assertSee('Rute hari ini')->assertSeeHtml('<b>1</b> dari 2 toko')
        ->assertSee('Sudah dibuat nota')
        ->assertSee('7 pcs')
        ->assertSee('Rp 36.000');
});

it('counts notes of every line toward the monthly target', function () {
    expect(SalesTarget::realized($this->sales->id))->toBe(36000);
});
