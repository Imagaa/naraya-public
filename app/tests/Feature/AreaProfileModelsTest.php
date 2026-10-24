<?php

use App\Enums\Role;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\User;

it('links a sales profile to its user and area', function () {
    $area = Area::create(['name' => 'Bekasi Timur']);
    $sales = User::factory()->create(['role' => Role::Sales]);

    $profile = SalesProfile::create([
        'user_id' => $sales->id,
        'area_id' => $area->id,
        'phone' => '081234567890',
        'joined_at' => now(),
        'monthly_target' => 40000000,
        'is_active' => true,
    ]);

    expect($profile->user->is($sales))->toBeTrue();
    expect($profile->area->name)->toBe('Bekasi Timur');
    expect($sales->salesProfile->id)->toBe($profile->id);
});

it('links a buyer profile to its user, area, and assigned sales', function () {
    $area = Area::create(['name' => 'Bekasi Timur']);
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User]);

    $profile = BuyerProfile::create([
        'user_id' => $buyer->id,
        'area_id' => $area->id,
        'sales_user_id' => $sales->id,
        'phone' => '081298765432',
        'address' => 'Jl. Contoh No. 1',
        'loyalty_points' => 0,
        'is_active' => true,
    ]);

    expect($profile->user->is($buyer))->toBeTrue();
    expect($profile->salesUser->is($sales))->toBeTrue();
    expect($buyer->buyerProfile->area->name)->toBe('Bekasi Timur');
});

it('finds the nearest area to a pin, or null when no area has coordinates', function () {
    \App\Models\Area::query()->delete();
    expect(\App\Models\Area::nearestTo(-6.2, 106.8))->toBeNull();

    $near = \App\Models\Area::factory()->create(['lat' => -6.20, 'lng' => 106.80]);
    \App\Models\Area::factory()->create(['lat' => -6.90, 'lng' => 107.60]);
    \App\Models\Area::factory()->create(['lat' => null, 'lng' => null]);

    expect(\App\Models\Area::nearestTo(-6.21, 106.81)->is($near))->toBeTrue();
});
