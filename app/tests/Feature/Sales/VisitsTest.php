<?php

use App\Enums\Role;
use App\Livewire\Sales\Visits\Index;
use App\Livewire\Sales\Visits\LogForm;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;

function visitStore(User $sales, string $name = 'Toko Saya'): User
{
    $buyer = User::factory()->create(['role' => Role::User, 'name' => $name]);
    $buyer->buyerProfile()->create(['sales_user_id' => $sales->id]);

    return $buyer;
}

it('logs a visit to one of the sales user\'s own stores', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = visitStore($sales);

    Livewire::actingAs($sales)->test(LogForm::class)
        ->set('buyerId', $buyer->id)
        ->set('notes', 'Stok menipis')
        ->call('logVisit')
        ->assertHasNoErrors()
        ->assertDispatched('visit-logged')
        ->assertSet('buyerId', null);

    $visit = Visit::where('sales_id', $sales->id)->firstOrFail();
    expect($visit->buyer_id)->toBe($buyer->id)->and($visit->notes)->toBe('Stok menipis');
});

it('requires a store to log a visit', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    Livewire::actingAs($sales)->test(LogForm::class)->call('logVisit')->assertHasErrors('buyerId');

    expect(Visit::count())->toBe(0);
});

it('refuses a visit to a store assigned to another sales user', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $other = visitStore(User::factory()->create(['role' => Role::Sales]));

    Livewire::actingAs($sales)->test(LogForm::class)
        ->set('buyerId', $other->id)
        ->call('logVisit')
        ->assertHasErrors(['buyerId' => 'exists']);

    expect(Visit::count())->toBe(0);
});

it('keeps the store when the form is fixed to one store', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = visitStore($sales);

    Livewire::actingAs($sales)->test(LogForm::class, ['buyerId' => $buyer->id, 'fixed' => true])
        ->assertDontSee('Cari toko')
        ->call('logVisit')
        ->assertSet('buyerId', $buyer->id);
});

it('lists only the sales user\'s own stores in the picker', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    visitStore($sales, 'Toko Milik Saya');
    visitStore(User::factory()->create(['role' => Role::Sales]), 'Toko Sales Lain');

    Livewire::actingAs($sales)->test(LogForm::class)
        ->assertSee('Toko Milik Saya')
        ->assertDontSee('Toko Sales Lain');
});

it('only shows visits logged by the current sales user', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);

    Visit::factory()->for($sales, 'sales')->create(['notes' => 'Kunjungan saya']);
    Visit::factory()->for($otherSales, 'sales')->create(['notes' => 'Kunjungan sales lain']);

    Livewire::actingAs($sales)->test(Index::class)
        ->assertSee('Kunjungan saya')
        ->assertDontSee('Kunjungan sales lain');
});

it('opens the form with the store from ?toko preselected', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = visitStore($sales);

    Livewire::withQueryParams(['toko' => $buyer->id])->actingAs($sales)->test(Index::class)
        ->assertSet('toko', $buyer->id)
        ->assertSee('showForm: true', false);
});

it('exposes the last visit by this sales user on assigned stores', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $other = User::factory()->create(['role' => Role::Sales]);
    $buyer = visitStore($sales);
    Visit::factory()->create(['sales_id' => $sales->id, 'buyer_id' => $buyer->id, 'visited_at' => now()->subDays(5)]);
    Visit::factory()->create(['sales_id' => $other->id, 'buyer_id' => $buyer->id, 'visited_at' => now()]);

    $row = User::assignedTo($sales->id)->firstOrFail();

    expect($row->last_visit_at->toDateString())->toBe(now()->subDays(5)->toDateString());
});

it('starts a fresh photo picker after each saved visit', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = visitStore($sales);

    Livewire::actingAs($sales)->test(LogForm::class)
        ->assertSeeHtml('wire:key="visit-photo-0"')
        ->set('buyerId', $buyer->id)
        ->call('logVisit')
        ->assertSeeHtml('wire:key="visit-photo-1"');
});
