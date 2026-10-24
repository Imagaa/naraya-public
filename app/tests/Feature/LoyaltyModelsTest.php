<?php

use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Models\LoyaltySetting;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;

it('seeds a single default loyalty_settings row via migration', function () {
    expect(LoyaltySetting::count())->toBe(1);
    expect(LoyaltySetting::first()->rupiah_per_point)->toBeGreaterThan(0);
});

it('casts redemption status to the enum and stores relations', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $reward = Reward::factory()->create(['point_cost' => 30]);

    $redemption = Redemption::factory()->create([
        'user_id' => $user->id,
        'reward_id' => $reward->id,
        'point_cost' => 30,
        'status' => RedemptionStatus::Disetujui,
    ]);

    expect($redemption->status)->toBe(RedemptionStatus::Disetujui);
    expect($redemption->user->id)->toBe($user->id);
    expect($redemption->reward->id)->toBe($reward->id);
});
