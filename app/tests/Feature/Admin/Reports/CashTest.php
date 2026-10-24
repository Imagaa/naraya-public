<?php

use App\Enums\CashCollectionStatus;
use App\Enums\CashDepositStatus;
use App\Enums\Role;
use App\Livewire\Admin\Reports\Cash;
use App\Models\CashCollection;
use App\Models\CashDeposit;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists deposits including disputed ones with their resolution note', function () {
    CashDeposit::factory()->create([
        'status' => CashDepositStatus::Resolved,
        'resolution_note' => 'Selisih dikonfirmasi',
        'created_at' => '2026-09-15',
    ]);
    CashDeposit::factory()->create(['created_at' => '2026-08-15']);

    $rows = Livewire::actingAs($this->admin)
        ->test(Cash::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->instance()
        ->rows();

    expect($rows)->toHaveCount(1);
    expect(collect($rows)->flatten()->implode(','))->toContain('Selisih dikonfirmasi');
});

it('shows current unremitted cash total, not date-filtered', function () {
    CashCollection::factory()->create(['status' => CashCollectionStatus::Held, 'amount' => 75000]);

    Livewire::actingAs($this->admin)
        ->test(Cash::class)
        ->assertSee('75.000');
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/cash')
        ->assertStatus(403);
});
