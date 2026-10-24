<?php

use App\Enums\CashDepositStatus;
use App\Enums\Role;
use App\Livewire\Admin\Deposits\Show;
use App\Models\CashDeposit;
use App\Models\User;
use Livewire\Livewire;

it('resolves a disputed deposit with a note and records who resolved it', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $deposit = CashDeposit::factory()->create(['status' => CashDepositStatus::Disputed]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['deposit' => $deposit])
        ->set('resolutionNote', 'Selisih karena kembalian belum dicatat, sudah dikonfirmasi ke sales.')
        ->call('resolveDispute');

    $deposit->refresh();
    expect($deposit->status)->toBe(CashDepositStatus::Resolved);
    expect($deposit->resolved_by)->toBe($admin->id);
    expect($deposit->resolution_note)->not->toBeNull();
    expect($deposit->resolved_at)->not->toBeNull();
});

it('requires a note to resolve a dispute', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $deposit = CashDeposit::factory()->create(['status' => CashDepositStatus::Disputed]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['deposit' => $deposit])
        ->call('resolveDispute')
        ->assertHasErrors('resolutionNote');

    expect($deposit->fresh()->status)->toBe(CashDepositStatus::Disputed);
});

it('cannot resolve a deposit that is not disputed', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $deposit = CashDeposit::factory()->create(['status' => CashDepositStatus::Verified]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['deposit' => $deposit])
        ->set('resolutionNote', 'x')
        ->call('resolveDispute')
        ->assertStatus(403);
});
