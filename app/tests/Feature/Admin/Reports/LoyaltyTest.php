<?php

use App\Enums\Role;
use App\Livewire\Admin\Reports\Loyalty;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists redemptions within the date range', function () {
    $reward = Reward::factory()->create(['name' => 'Payung']);
    $buyer = User::factory()->create(['role' => Role::User]);

    Redemption::factory()->create([
        'user_id' => $buyer->id, 'reward_id' => $reward->id, 'created_at' => '2026-09-15',
    ]);
    Redemption::factory()->create([
        'user_id' => $buyer->id, 'reward_id' => $reward->id, 'created_at' => '2026-08-15',
    ]);

    $rows = Livewire::actingAs($this->admin)
        ->test(Loyalty::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->instance()
        ->rows();

    expect($rows)->toHaveCount(1);
    expect(collect($rows)->flatten()->implode(','))->toContain('Payung');
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/loyalty')
        ->assertStatus(403);
});
