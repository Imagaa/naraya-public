<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Enums\StockTransferKind;
use App\Livewire\Admin\Stock\Carried;
use App\Livewire\Admin\Stock\GoodsIn;
use App\Livewire\Admin\Stock\Transfers;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->mie = Product::factory()->create(['name' => 'Mie Goreng', 'stock' => 10, 'price' => 3000]);
    $this->toy = Product::factory()->create(['name' => 'Mobil Remote', 'stock' => 3, 'price' => 50000, 'line' => BusinessLine::Toys]);
});

it('records goods in as whole units plus loose pcs, kept in pcs', function () {
    $lembar = \App\Models\Product::factory()->create(['name' => 'Kartu Mainan', 'stock' => 0, 'price' => 12000, 'unit' => 'lembar', 'pieces_per_unit' => 12]);

    Livewire::actingAs($this->admin)->test(GoodsIn::class)
        ->assertSee('1 lembar = 12 pcs')
        ->set('rows', [['product_id' => $lembar->id, 'qty' => 2, 'pcs' => 5]])->call('save')->assertHasNoErrors();

    expect($lembar->fresh()->stock)->toBe(29)
        ->and($lembar->fresh()->qtyLabel(29))->toBe('2 lembar 5 pcs')
        ->and(\App\Models\StockTransfer::sole()->totalValue())->toBe(29000); // 29 × 12.000 / 12
});

it('records goods in from several rows, adding up duplicate products', function () {
    Livewire::actingAs($this->admin)->test(GoodsIn::class)
        ->assertSee('Mie Goreng')->assertDontSee('Mobil Remote')
        ->set('rows', [['product_id' => $this->mie->id, 'qty' => 5], ['product_id' => $this->mie->id, 'qty' => 2]])
        ->set('note', 'SJ-77')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('rows', [['product_id' => '', 'qty' => '', 'pcs' => '']])
        ->assertSee('SJ-77');

    expect($this->mie->fresh()->stock)->toBe(17)
        ->and(StockTransfer::sole()->items->sole()->quantity)->toBe(7);
});

it('rejects an empty or invalid goods-in form', function () {
    Livewire::actingAs($this->admin)->test(GoodsIn::class)
        ->set('rows', [['product_id' => '', 'qty' => '']])
        ->call('save')->assertHasErrors('items');

    Livewire::actingAs($this->admin)->test(GoodsIn::class)
        ->set('rows', [['product_id' => $this->mie->id, 'qty' => 0]])
        ->call('save')->assertHasErrors();
    expect($this->mie->fresh()->stock)->toBe(10);
});

it('records goods in for the Toys line when admin works in Toys', function () {
    ActiveLine::set(BusinessLine::Toys);

    Livewire::actingAs($this->admin)->test(GoodsIn::class)
        ->assertSee('Mobil Remote')->assertDontSee('Mie Goreng')
        ->set('rows', [['product_id' => $this->toy->id, 'qty' => 4]])
        ->call('save')->assertHasNoErrors();

    expect($this->toy->fresh()->stock)->toBe(7)->and(StockTransfer::sole()->line)->toBe(BusinessLine::Toys);
});

it('lists every sales movement of the active line, filterable by kind and sales', function () {
    $transfers = app(StockTransfers::class);
    $transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 4]);
    $transfers->returnGoods($this->sales, BusinessLine::Naraya, [$this->mie->id => 1]);
    $transfers->pickup($this->sales, BusinessLine::Toys, [$this->toy->id => 1]);

    Livewire::actingAs($this->admin)->test(Transfers::class)
        ->assertSee('Budi')->assertSee('Pengambilan')->assertSee('Retur ke gudang')->assertDontSee('Mobil Remote')
        ->call('setFilter', 'retur')
        ->assertViewHas('transfers', fn ($p) => $p->total() === 1 && $p->first()->kind === StockTransferKind::Retur);
});

it('shows carried stock per sales and records a stock check shortfall', function () {
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 4]);

    Livewire::actingAs($this->admin)->test(Carried::class)
        ->assertSee('Budi')->assertSee('Rp 12.000')
        ->call('startCheck', $this->sales->id)
        ->assertSet("counted.{$this->mie->id}", 4)
        ->set("counted.{$this->mie->id}", 3)
        ->call('saveCheck')
        ->assertHasNoErrors();

    expect(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->mie->id => 3])
        ->and(StockTransfer::where('kind', StockTransferKind::Opname)->sole()->items->sole()->quantity)->toBe(1);
});

it('is admin only', function () {
    $this->actingAs($this->sales)->get('http://'.config('app.admin_domain').'/barang-masuk')->assertForbidden();
});
