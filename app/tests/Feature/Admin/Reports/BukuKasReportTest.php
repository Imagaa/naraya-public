<?php

use App\Enums\CashBookEntryType;
use App\Enums\Role;
use App\Livewire\Admin\Reports\BukuKas;
use App\Models\CashBookEntry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists cash book entries within the date range', function () {
    CashBookEntry::factory()->create([
        'type' => CashBookEntryType::Pemasukan, 'amount' => 100000, 'date' => '2026-09-15',
    ]);
    CashBookEntry::factory()->create([
        'type' => CashBookEntryType::Pemasukan, 'amount' => 999999, 'date' => '2026-08-15',
    ]);

    $rows = Livewire::actingAs($this->admin)
        ->test(BukuKas::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->instance()
        ->rows();

    expect($rows)->toHaveCount(1);
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/buku-kas')
        ->assertStatus(403);
});
