<?php

use App\Enums\CashDepositStatus;
use App\Enums\Role;
use App\Livewire\Admin\Deposits\Index;
use App\Livewire\Admin\Deposits\Show;
use App\Models\CashBookEntry;
use App\Models\CashDeposit;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists deposits', function () {
    $deposit = CashDeposit::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('#'.$deposit->id);
});

it('verifies a deposit as matching when claimed equals expected', function () {
    $deposit = CashDeposit::factory()->create([
        'expected_amount' => 50000, 'claimed_amount' => 50000, 'status' => CashDepositStatus::Submitted,
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['deposit' => $deposit])->call('verify');

    expect($deposit->fresh()->status)->toBe(CashDepositStatus::Verified);
});

it('flags a deposit as disputed when claimed does not match expected', function () {
    $deposit = CashDeposit::factory()->create([
        'expected_amount' => 50000, 'claimed_amount' => 45000, 'status' => CashDepositStatus::Submitted,
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['deposit' => $deposit])->call('verify');

    expect($deposit->fresh()->status)->toBe(CashDepositStatus::Disputed);
});

it('blocks non-admin roles from the deposits index', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/deposits')
        ->assertStatus(403);
});

// The cash book entry now comes from the nightly summary (DailyPoster); verify only stamps verified_at.
it('stamps verified_at, without writing a cash book row, when a deposit is verified as matching', function () {
    $deposit = CashDeposit::factory()->create([
        'expected_amount' => 50000, 'claimed_amount' => 50000, 'status' => CashDepositStatus::Submitted,
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['deposit' => $deposit])->call('verify');

    expect($deposit->fresh()->verified_at)->not->toBeNull();
    expect(CashBookEntry::count())->toBe(0);
});

it('stamps resolved_at, without writing a cash book row, when a dispute is resolved', function () {
    $deposit = CashDeposit::factory()->create([
        'expected_amount' => 50000, 'claimed_amount' => 45000, 'status' => CashDepositStatus::Submitted,
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['deposit' => $deposit])->call('verify');

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['deposit' => $deposit->fresh()])
        ->set('resolutionNote', 'Selisih sudah dikonfirmasi ke sales.')
        ->call('resolveDispute');

    expect($deposit->fresh()->resolved_at)->not->toBeNull();
    expect(CashBookEntry::count())->toBe(0);
});

it('does not record cash book income while a deposit is only disputed', function () {
    $deposit = CashDeposit::factory()->create([
        'expected_amount' => 50000, 'claimed_amount' => 45000, 'status' => CashDepositStatus::Submitted,
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['deposit' => $deposit])->call('verify');

    expect(CashBookEntry::where('reference', 'Setoran #'.$deposit->id)->exists())->toBeFalse();
});
