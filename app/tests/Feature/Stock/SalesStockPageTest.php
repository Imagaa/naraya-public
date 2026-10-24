<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Livewire\Sales\Stock\Index;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Livewire\Livewire;

beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->mie = Product::factory()->create(['name' => 'Mie Goreng', 'stock' => 10, 'price' => 3000]);
    $this->kopi = Product::factory()->create(['name' => 'Kopi Sachet', 'stock' => 0, 'price' => 2000]);
    $this->toy = Product::factory()->create(['name' => 'Mobil Remote', 'stock' => 3, 'price' => 50000, 'line' => BusinessLine::Toys]);
});

it('picks up goods in two steps: choose, then confirm', function () {
    Livewire::actingAs($this->sales)->test(Index::class, ['mode' => 'ambil'])
        ->assertSee('Mie Goreng')->assertDontSee('Mobil Remote')
        ->set("qty.{$this->mie->id}", 4)
        ->call('review')
        ->assertSet('step', 'konfirmasi')
        ->assertSee('Mie Goreng · 4 pcs')
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertDispatched('sheet-done', message: 'Barang diambil: 4 pcs.');

    expect(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->mie->id => 4])
        ->and($this->mie->fresh()->stock)->toBe(6);
});

it('does not offer products the warehouse has run out of, and stops a pickup over the stock', function () {
    Livewire::actingAs($this->sales)->test(Index::class, ['mode' => 'ambil'])
        ->assertDontSee('Kopi Sachet')
        ->set("qty.{$this->mie->id}", 11)
        ->call('review')
        ->assertHasErrors("qty.{$this->mie->id}")
        ->assertSet('step', 'pilih');
});

it('asks for at least one item before reviewing', function () {
    Livewire::actingAs($this->sales)->test(Index::class, ['mode' => 'ambil'])->call('review')->assertHasErrors('qty');
});

it('returns carried goods, capped at what is carried', function () {
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 4]);

    Livewire::actingAs($this->sales)->test(Index::class, ['mode' => 'retur'])
        ->set("qty.{$this->mie->id}", 5)->call('review')->assertHasErrors("qty.{$this->mie->id}")
        ->set("qty.{$this->mie->id}", 3)->call('review')->call('confirm')
        ->assertHasNoErrors();

    expect(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->mie->id => 1]);
});

it('works in the active line', function () {
    ActiveLine::set(BusinessLine::Toys);

    Livewire::actingAs($this->sales)->test(Index::class, ['mode' => 'ambil'])
        ->assertSee('Mobil Remote')->assertDontSee('Mie Goreng')
        ->set("qty.{$this->toy->id}", 1)->call('review')->call('confirm')->assertHasNoErrors();

    expect(CarriedStock::for($this->sales, BusinessLine::Toys)->all())->toBe([$this->toy->id => 1]);
});

it('requires goods at check-in, then goes straight to the camera with the picked stock', function () {
    \App\Models\AttendanceLocation::create(['nama' => 'Gudang', 'lat' => -6.2, 'lng' => 106.8, 'radius_m' => 100]);

    Livewire::actingAs($this->sales)->test(Index::class, ['mode' => 'masuk'])
        ->assertSee('Lanjut ke foto absen')->assertDontSee('Tanpa barang')
        ->call('review')->assertHasErrors('qty')->assertSet('step', 'pilih')
        ->set("qty.{$this->mie->id}", 2)->call('review')
        ->assertSet('step', 'foto')->assertSee('Mie Goreng · 2 pcs')
        ->assertViewHas('cfg', fn ($cfg) => (array) $cfg['items'] === [$this->mie->id => 2]);

    expect(\App\Models\StockTransfer::count())->toBe(0); // recorded by the attendance submit, not here
});

it('searches the pickup list and pages it, keeping quantities typed on other pages', function () {
    foreach (range(1, 20) as $i) {
        Product::factory()->create(['name' => sprintf('Biskuit Rasa %02d', $i), 'stock' => 50, 'price' => 1000, 'sku' => sprintf('BIS-%02d', $i)]);
    }

    $page = Livewire::actingAs($this->sales)->test(Index::class, ['mode' => 'ambil'])
        ->assertSee('Biskuit Rasa 01')->assertDontSee('Mie Goreng')   // 21 in stock, 15 per page, by name: Mie is on page 2
        ->set('qty.'.Product::where('name', 'Biskuit Rasa 01')->value('id'), 3)
        ->call('gotoPage', 2, 'hal')
        ->assertSee('Mie Goreng')->assertSee('1 barang dipilih')
        ->set('qty.'.$this->mie->id, 2)
        ->set('search', 'mie')
        ->assertSee('Mie Goreng')->assertDontSee('Biskuit Rasa 01')
        ->set('search', 'BIS-07')
        ->assertSee('Biskuit Rasa 07')->assertDontSee('Biskuit Rasa 08')
        ->set('search', 'tidak ada')
        ->assertSee('Tidak ada barang yang cocok')
        ->set('search', '')
        ->call('review')
        ->assertSee('Biskuit Rasa 01 · 3 pcs')->assertSee('Mie Goreng · 2 pcs');
});
