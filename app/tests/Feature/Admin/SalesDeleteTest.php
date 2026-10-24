<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Admin\Sales\Form;
use App\Livewire\Admin\Sales\Index;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\SalesProfile;
use App\Models\User;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Livewire\Livewire;

// Deleting a sales account keeps their history (user, 2026-10-12).
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->budi = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Santoso']);
    SalesProfile::factory()->create(['user_id' => $this->budi->id, 'area_id' => null]);
    $this->store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->budi->id, 'latitude' => -6.2, 'longitude' => 106.8, 'consignment_opened_at' => now()]);
    $this->mie = Product::factory()->create(['line' => 'naraya', 'stock' => 50]);
});

it('refuses while the sales person still holds goods or cash', function () {
    app(StockTransfers::class)->pickup($this->budi, BusinessLine::Naraya, [$this->mie->id => 5]);
    Livewire::actingAs($this->admin)->test(Form::class, ['sales' => $this->budi])->call('deleteAccount')->assertHasErrors('delete');

    app(SalesNotes::class)->create($this->budi, BusinessLine::Naraya, $this->store->id, [$this->mie->id => 5], NotePaymentMethod::Cash);
    Livewire::actingAs($this->admin)->test(Form::class, ['sales' => $this->budi])->call('deleteAccount')->assertHasErrors('delete');

    expect($this->budi->fresh()->account_deleted_at)->toBeNull();
});

it('removes the login and the lists but keeps every record under their name', function () {
    $note = SalesNote::create(['line' => 'naraya', 'sales_id' => $this->budi->id, 'buyer_id' => $this->store->id, 'payment_method' => NotePaymentMethod::Transfer, 'source' => 'bawaan', 'total' => 1000]);

    Livewire::actingAs($this->admin)->test(Form::class, ['sales' => $this->budi])->call('deleteAccount')
        ->assertHasNoErrors()->assertRedirect(route('admin.sales.index'));

    $budi = $this->budi->fresh();
    expect($budi->account_deleted_at)->not->toBeNull()
        ->and($budi->password)->toBeNull()->and($budi->email)->toBeNull()
        ->and($budi->salesProfile->is_active)->toBeFalse()
        ->and($this->store->buyerProfile->fresh()->sales_user_id)->toBeNull()
        ->and($note->fresh()->salesUser->name)->toBe('Budi Santoso')
        ->and(User::currentSales()->count())->toBe(0);

    \App\Support\SalesAreas::refreshAll(); // a deleted sales person gets no area back
    expect(\App\Models\Area::count())->toBe(0);

    Livewire::actingAs($this->admin)->test(Index::class)->assertViewHas('salesUsers', fn ($list) => $list->isEmpty());
    $this->actingAs($budi)->get(salesUrl('/'))->assertForbidden(); // an open session ends too
});
