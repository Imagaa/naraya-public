<?php

use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Livewire\Marketplace\Rewards\Index;
use App\Models\BuyerProfile;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Livewire\Livewire;

it('lets a buyer redeem a reward when they have enough points', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 50]);
    $reward = Reward::factory()->create(['point_cost' => 30, 'stock' => 5]);

    Livewire::actingAs($buyer)
        ->test(Index::class)
        ->call('redeem', $reward);

    $redemption = Redemption::where('user_id', $buyer->id)->firstOrFail();
    expect($redemption->status)->toBe(RedemptionStatus::Diajukan);
    expect($redemption->reward_id)->toBe($reward->id);
    expect($redemption->point_cost)->toBe(30);
});

it('blocks redeeming when the buyer does not have enough points', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 10]);
    $reward = Reward::factory()->create(['point_cost' => 30, 'stock' => 5]);

    Livewire::actingAs($buyer)
        ->test(Index::class)
        ->call('redeem', $reward)
        ->assertHasErrors('redeem');

    expect(Redemption::count())->toBe(0);
});

it('blocks redeeming when the reward is out of stock', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 100]);
    $reward = Reward::factory()->create(['point_cost' => 30, 'stock' => 0]);

    Livewire::actingAs($buyer)
        ->test(Index::class)
        ->call('redeem', $reward)
        ->assertHasErrors('redeem');

    expect(Redemption::count())->toBe(0);
});

it('shows the buyer their own redemption history', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $reward = Reward::factory()->create(['name' => 'Kaos Naraya']);
    Redemption::factory()->create(['user_id' => $buyer->id, 'reward_id' => $reward->id]);

    Livewire::actingAs($buyer)
        ->test(Index::class)
        ->assertSee('Kaos Naraya');
});
