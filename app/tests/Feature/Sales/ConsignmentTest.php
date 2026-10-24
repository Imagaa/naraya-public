<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Sales\Buyers\Show as StoreSheet;
use App\Livewire\Sales\Consignment\Opening;
use App\Livewire\Sales\Consignment\Visit;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use App\Support\Sales\Consignments;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// Titip jual
beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Warung Bu Ani']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id, 'consignment_opened_at' => null]);
    // 1 lembar = 12 pcs at Rp 12.000 a lembar: Rp 1.000 a pcs
    $this->kartu = Product::factory()->create(['name' => 'Kartu Mainan', 'stock' => 120, 'price' => 12000, 'unit' => 'lembar', 'pieces_per_unit' => 12]);
    $this->inv = app(Consignments::class);
});

it('locks a store until its first count, which happens once', function () {
    Livewire::actingAs($this->sales)->test(StoreSheet::class, ['buyer' => $this->store->id])
        ->assertSee('Catat barang di toko')->assertDontSee('Kunjungan titip jual');
    expect(fn () => app(SalesNotes::class)->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->kartu->id => 1], NotePaymentMethod::Cash))
        ->toThrow(ValidationException::class);

    Livewire::actingAs($this->sales)->test(Opening::class, ['storeId' => $this->store->id])
        ->set("qty.{$this->kartu->id}", 30)->call('save')->assertHasNoErrors()
        ->assertDispatched('sheet-open', sheet: 'toko', for: $this->store->id);

    expect(Consignments::atStore($this->store->id, BusinessLine::Naraya)->all())->toBe([$this->kartu->id => 30]);
    Livewire::actingAs($this->sales)->test(StoreSheet::class, ['buyer' => $this->store->id])
        ->assertSee('Kunjungan titip jual')->assertSee('2 lembar 6 pcs');
    expect(fn () => $this->inv->open($this->sales, $this->store->id, BusinessLine::Naraya, []))->toThrow(ValidationException::class);
});

it('turns what went missing into a nota, takes everything left back, and leaves a fresh lot from carried stock', function () {
    $this->inv->open($this->sales, $this->store->id, BusinessLine::Naraya, [$this->kartu->id => 30]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$this->kartu->id => 24]);

    // 30 at the store, 20 counted → 10 sold; the 20 left come back; 12 left from the 24 carried
    $note = $this->inv->visit($this->sales, $this->store->id, BusinessLine::Naraya,
        [$this->kartu->id => 20], [$this->kartu->id => 12], NotePaymentMethod::Cash);

    expect($note)->source->toBe('titipan')->total->toBe(10000)
        ->and($note->items->sole()->quantity)->toBe(10)
        ->and(Consignments::atStore($this->store->id, BusinessLine::Naraya)->all())->toBe([$this->kartu->id => 12])   // only the fresh lot
        ->and(Consignments::returnsHeld($this->sales, BusinessLine::Naraya)->all())->toBe([$this->kartu->id => 20])
        ->and(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->kartu->id => 12]); // 24 − 12; the sold ones were never carried
});

it('refuses counts above the books and leaving more than carried', function () {
    $this->inv->open($this->sales, $this->store->id, BusinessLine::Naraya, [$this->kartu->id => 5]);
    $visit = fn (array $rem, array $tit = []) => $this->inv->visit($this->sales, $this->store->id, BusinessLine::Naraya, $rem, $tit, NotePaymentMethod::Cash);

    expect(fn () => $visit([$this->kartu->id => 6]))->toThrow(ValidationException::class)
        ->and(fn () => $visit([], [$this->kartu->id => 1]))->toThrow(ValidationException::class)
        ->and(SalesNote::count())->toBe(0);

    expect($visit([]))->toBeNull(); // nothing sold: just the visit
    expect(\App\Models\Visit::where('buyer_id', $this->store->id)->count())->toBe(1);
});

it('runs the visit sheet in three steps and opens the receipt', function () {
    $this->inv->open($this->sales, $this->store->id, BusinessLine::Naraya, [$this->kartu->id => 24]);

    $page = Livewire::actingAs($this->sales)->test(Visit::class, ['storeId' => $this->store->id])
        ->assertSee('Dititip 2 lembar')->assertSee('= 24 pcs')->assertSee('Barang yang ada di toko')->assertSet("remaining.{$this->kartu->id}", 24)
        ->set("remaining.{$this->kartu->id}", 18)->call('next')->assertSet('step', 'titip')
        ->call('next')->assertSet('step', 'bayar')
        ->assertSee('Kartu Mainan · 6 pcs')->assertSee('Rp 6.000')->assertSee('Dibawa kembali (retur)')->assertSee('Dititip hari ini')
        ->call('save')->assertHasNoErrors();

    $page->assertDispatched('sheet-open', sheet: 'nota-detail', for: SalesNote::sole()->id);
});
