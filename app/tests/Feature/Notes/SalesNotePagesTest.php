<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Sales\Notes\Create;
use App\Livewire\Sales\Notes\Index;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
    $this->foreign = User::factory()->create(['role' => Role::User, 'name' => 'Toko Lain']);
    BuyerProfile::factory()->create(['user_id' => $this->foreign->id]);
    $this->mie = Product::factory()->create(['name' => 'Mie Goreng', 'stock' => 20, 'price' => 3000]);
    $this->kopi = Product::factory()->create(['name' => 'Kopi Sachet', 'stock' => 20, 'price' => 2000]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 10]);
});

it('creates a cash note in two steps and then shows its receipt', function () {
    Livewire::actingAs($this->sales)->test(Create::class, ['toko' => $this->store->id])
        ->assertSee('Toko Maju')->assertDontSee('Toko Lain')
        ->assertSee('Mie Goreng')->assertDontSee('Kopi Sachet')      // only what I carry
        ->set("qty.{$this->mie->id}", 4)
        ->set('method', 'cash')
        ->call('review')
        ->assertSet('step', 'konfirmasi')
        ->assertSee('Rp 12.000')
        ->call('save')
        ->assertDispatched('sheet-open', sheet: 'nota-detail', for: SalesNote::sole()->id);

    expect(SalesNote::sole()->total)->toBe(12000);
});

it('asks for a store, an item and, for transfer/QRIS, a proof photo', function () {
    Livewire::actingAs($this->sales)->test(Create::class)
        ->set("qty.{$this->mie->id}", 1)->call('review')->assertHasErrors('buyerId');

    Livewire::actingAs($this->sales)->test(Create::class, ['toko' => $this->store->id])
        ->call('review')->assertHasErrors('qty');

    Livewire::actingAs($this->sales)->test(Create::class, ['toko' => $this->store->id])
        ->set("qty.{$this->mie->id}", 11)->call('review')->assertHasErrors("qty.{$this->mie->id}");

    Livewire::actingAs($this->sales)->test(Create::class, ['toko' => $this->store->id])
        ->set("qty.{$this->mie->id}", 1)->set('method', 'transfer')
        ->call('review')->assertHasErrors('proof');
});

it('saves a transfer note with its proof photo, and no longer offers QRIS', function () {
    Livewire::actingAs($this->sales)->test(Create::class, ['toko' => $this->store->id])
        ->assertDontSee('QRIS')
        ->set("qty.{$this->mie->id}", 2)->set('method', 'transfer')->assertSee('BNI 0000000000 a.n. Nama Pemilik')
        ->set('proof', UploadedFile::fake()->image('bukti.jpg'))
        ->call('review')->call('save')->assertHasNoErrors();

    expect(SalesNote::sole()->payment_method)->toBe(NotePaymentMethod::Transfer)->and(SalesNote::sole()->proof_path)->not->toBeNull();

    Livewire::actingAs($this->sales)->test(Create::class, ['toko' => $this->store->id])
        ->set("qty.{$this->mie->id}", 1)->set('method', 'qris')->call('review')->assertHasErrors('method');
});

it('shows the nota to its sales only, with a share text', function () {
    $note = app(SalesNotes::class)->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->mie->id => 3], NotePaymentMethod::Cash);

    Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\Notes\Show::class, ['id' => $note->id])
        ->assertSee($note->number())->assertSee('Toko Maju')->assertSee('Mie Goreng')->assertSee('Rp 9.000')->assertSee('Bagikan');
    $this->actingAs($this->sales)->get(salesUrl('/nota/'.$note->id))->assertRedirect(route('sales.more', ['buka' => 'nota-detail', 'id' => $note->id]));

    $other = User::factory()->create(['role' => Role::Sales]);
    Livewire::actingAs($other)->test(\App\Livewire\Sales\Notes\Show::class, ['id' => $note->id])->assertNotFound();
});

it('lists my notes in the active line', function () {
    $toy = Product::factory()->create(['name' => 'Mobil', 'stock' => 5, 'price' => 50000, 'line' => BusinessLine::Toys]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Toys, [$toy->id => 1]);
    $n = app(SalesNotes::class)->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->mie->id => 1], NotePaymentMethod::Cash);
    $t = app(SalesNotes::class)->create($this->sales, BusinessLine::Toys, $this->store->id, [$toy->id => 1], NotePaymentMethod::Cash);

    Livewire::actingAs($this->sales)->test(Index::class)->assertSee($n->number())->assertDontSee($t->number());
    ActiveLine::set(BusinessLine::Toys);
    Livewire::actingAs($this->sales)->test(Index::class)->assertSee($t->number())->assertDontSee($n->number());
});
