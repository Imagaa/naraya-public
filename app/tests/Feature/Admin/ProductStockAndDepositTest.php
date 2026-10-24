<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Admin\Products\Form;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\User;
use App\Support\Sales\CashHeld;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('sets the stock to what was counted and logs the difference as a correction', function () {
    $product = Product::factory()->create(['stock' => 40]);

    Livewire::actingAs($this->admin)->test(Form::class, ['product' => $product])
        ->call('correctStock')->assertHasErrors(['countedStock', 'stockNote'])
        ->set('countedStock', 25)->set('stockNote', 'Stok opname')->call('correctStock')->assertHasNoErrors()
        ->assertSet('stock', 25);

    expect($product->fresh()->stock)->toBe(25)
        ->and($product->stockMovements()->sole())->quantity->toBe(-15)->note->toBe('Stok opname');
});

it('deletes an unused product and refuses one already sold', function () {
    $unused = Product::factory()->create();
    Livewire::actingAs($this->admin)->test(Form::class, ['product' => $unused])->call('destroy')->assertRedirect(route('admin.products.index'));
    expect(Product::find($unused->id))->toBeNull();

    $sales = User::factory()->create(['role' => Role::Sales]);
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $sales->id]);
    $sold = Product::factory()->create(['stock' => 10, 'line' => 'naraya']);
    app(StockTransfers::class)->pickup($sales, BusinessLine::Naraya, [$sold->id => 4]);

    Livewire::actingAs($this->admin)->test(Form::class, ['product' => $sold])->call('destroy')->assertHasErrors('destroy');
    expect(Product::find($sold->id))->not->toBeNull();
});

it('makes the sales person owe the deposit price for cash notes, not the selling price', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $sales->id]);
    $card = Product::factory()->create(['stock' => 100, 'line' => 'naraya', 'price' => 12000, 'deposit_price' => 9000, 'pieces_per_unit' => 12, 'unit' => 'lembar']);
    app(StockTransfers::class)->pickup($sales, BusinessLine::Naraya, [$card->id => 24]);

    $note = app(SalesNotes::class)->create($sales, BusinessLine::Naraya, $store->id, [$card->id => 18], NotePaymentMethod::Cash);

    expect($note->total)->toBe(18000)            // 1½ lembar at Rp 12.000
        ->and($note->deposit_total)->toBe(13500) // at Rp 9.000 per lembar
        ->and(CashHeld::for($sales))->toBe(13500);
});
