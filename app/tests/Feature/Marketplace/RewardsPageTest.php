<?php

use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Livewire\Marketplace\Rewards\Index;
use App\Models\BuyerProfile;
use App\Models\LoyaltySetting;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    LoyaltySetting::first()?->update(['rupiah_per_point' => 10000]) ?? LoyaltySetting::create(['rupiah_per_point' => 10000]);
    Reward::query()->delete();
    $this->buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $this->buyer->id, 'loyalty_points' => 1240]);
    $this->actingAs($this->buyer);
});

it('shows points, the earn rate and the next reward to aim for', function () {
    Reward::factory()->create(['name' => 'Payung', 'point_cost' => 400, 'stock' => 5]);
    Reward::factory()->create(['name' => 'Rice Cooker', 'point_cost' => 1500, 'stock' => 3]);
    Reward::factory()->create(['name' => 'Kompor', 'point_cost' => 2500, 'stock' => 2]);

    $this->get(route('rewards.index'))
        ->assertOk()
        ->assertSee('<body class="mp" data-line="', false)
        ->assertSee('1.240')
        ->assertSee('1 poin untuk tiap 150 pcs mainan Naraya Toys yang terjual di toko Anda.')
        ->assertSee('0 / 150 pcs menuju poin berikutnya')
        ->assertSee('Kurang 260 poin lagi')
        ->assertSee('Kurang 1.260 poin')
        ->assertSee('role="meter"', false)
        ->assertDontSee('<table', false);
});

it('offers Tukar on affordable rewards and marks sold-out ones', function () {
    Reward::factory()->create(['name' => 'Payung', 'point_cost' => 400, 'stock' => 5]);
    Reward::factory()->create(['name' => 'Kaos', 'point_cost' => 300, 'stock' => 0]);

    Livewire::test(Index::class)
        ->assertSeeHtml('wire:click="redeem(')
        ->assertSee('Stok habis');
});

it('hides the next-reward block when every reward is affordable', function () {
    Reward::factory()->create(['point_cost' => 400, 'stock' => 5]);

    Livewire::test(Index::class)->assertDontSee('poin lagi');
});

it('lists redemption history as cards with a status pill', function () {
    $reward = Reward::factory()->create(['name' => 'Voucher Belanja', 'point_cost' => 1000]);
    Redemption::create(['user_id' => $this->buyer->id, 'reward_id' => $reward->id, 'point_cost' => 1000, 'status' => RedemptionStatus::Ditolak]);

    Livewire::test(Index::class)
        ->assertSee('Voucher Belanja')
        ->assertSee('status status-sale', false)
        ->assertSeeHtml('<s class="num">−1.000</s>');
});

it('disables rewards the buyer cannot afford and shows the shortfall', function () {
    $this->buyer->buyerProfile->update(['loyalty_points' => 20]);
    $reward = Reward::factory()->create(['name' => 'Payung', 'point_cost' => 50, 'stock' => 3]);

    Livewire::test(Index::class)
        ->assertSee('Kurang 30 poin')
        ->assertDontSeeHtml('wire:click="redeem('.$reward->id.')"');
});
