<?php

use App\Enums\Role;
use App\Models\CashCollection;
use App\Models\CashDeposit;
use App\Models\User;

it('links collections to their deposit and sales user', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $deposit = CashDeposit::factory()->create([
        'sales_id' => $sales->id,
        'expected_amount' => 50000,
        'claimed_amount' => 50000,
    ]);
    $collection = CashCollection::factory()->create([
        'sales_id' => $sales->id,
        'cash_deposit_id' => $deposit->id,
        'amount' => 50000,
    ]);

    expect($collection->deposit->is($deposit))->toBeTrue();
    expect($deposit->collections->first()->is($collection))->toBeTrue();
    expect($collection->salesUser->is($sales))->toBeTrue();
});
