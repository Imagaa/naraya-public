<?php

use App\Enums\CashCollectionStatus;
use App\Enums\Role;
use App\Livewire\Sales\Deposits\Index;
use App\Models\CashCollection;
use App\Models\CashDeposit;
use App\Models\User;
use Livewire\Livewire;

it('submits a deposit including all held collections and marks them Deposited', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $c1 = CashCollection::factory()->create(['sales_id' => $sales->id, 'amount' => 20000, 'status' => CashCollectionStatus::Held]);
    $c2 = CashCollection::factory()->create(['sales_id' => $sales->id, 'amount' => 15000, 'status' => CashCollectionStatus::Held]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->set('claimedAmount', 35000)
        ->call('submitDeposit');

    $deposit = CashDeposit::where('sales_id', $sales->id)->firstOrFail();
    expect($deposit->expected_amount)->toBe(35000);
    expect($deposit->claimed_amount)->toBe(35000);
    expect($c1->fresh()->status)->toBe(CashCollectionStatus::Deposited);
    expect($c1->fresh()->cash_deposit_id)->toBe($deposit->id);
    expect($c2->fresh()->status)->toBe(CashCollectionStatus::Deposited);
});

it('rejects a deposit submission when nothing is held', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->set('claimedAmount', 10000)
        ->call('submitDeposit')
        ->assertHasErrors('claimedAmount');

    expect(CashDeposit::count())->toBe(0);
});

it('does not include another sales user\'s held collections', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);
    CashCollection::factory()->create(['sales_id' => $otherSales->id, 'amount' => 99000, 'status' => CashCollectionStatus::Held]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->set('claimedAmount', 0)
        ->call('submitDeposit')
        ->assertHasErrors('claimedAmount'); // nothing held for *this* sales user
});

it('pre-fills the deposit amount with the cash held', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    CashCollection::factory()->create(['sales_id' => $sales->id, 'amount' => 20000, 'status' => CashCollectionStatus::Held]);
    CashCollection::factory()->create(['sales_id' => $sales->id, 'amount' => 15000, 'status' => CashCollectionStatus::Held]);

    Livewire::actingAs($sales)->test(Index::class)
        ->assertSet('claimedAmount', 35000)
        ->assertSee('Pesanan #');
});

it('colours each deposit status differently', function () {
    expect(\App\Enums\CashDepositStatus::Submitted->variant())->toBe('accent')
        ->and(\App\Enums\CashDepositStatus::Verified->variant())->toBe('success')
        ->and(\App\Enums\CashDepositStatus::Disputed->variant())->toBe('danger')
        ->and(\App\Enums\CashDepositStatus::Resolved->variant())->toBe('neutral');
});
