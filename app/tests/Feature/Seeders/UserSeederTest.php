<?php

use App\Enums\Role;
use App\Models\Area;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\UserSeeder;

it('seeds areas and the fixed demo accounts with profiles', function () {
    (new AreaSeeder())->run();
    (new UserSeeder())->run();

    expect(Area::count())->toBe(10);

    $admin = User::where('email', 'admin@naraya.test')->first();
    expect($admin)->not->toBeNull();
    expect($admin->role)->toBe(Role::Admin);

    $gudang1 = User::where('email', 'gudang1@naraya.test')->first();
    expect($gudang1)->not->toBeNull();
    expect($gudang1->role)->toBe(Role::Gudang);

    $sales1 = User::where('email', 'sales1@naraya.test')->first();
    expect($sales1)->not->toBeNull();
    expect($sales1->role)->toBe(Role::Sales);
    expect($sales1->salesProfile)->not->toBeNull();

    $buyer1 = User::where('email', 'buyer1@naraya.test')->first();
    expect($buyer1)->not->toBeNull();
    expect($buyer1->role)->toBe(Role::User);
    expect($buyer1->buyerProfile)->not->toBeNull();
    expect($buyer1->buyerProfile->loyalty_points)->toBeGreaterThan(0);

    expect(User::where('role', Role::Sales)->count())->toBe(10);
    expect(User::where('role', Role::User)->count())->toBe(58);

    // at least one buyer with zero points (empty-state / edge case coverage)
    expect(User::where('role', Role::User)->whereHas('buyerProfile', fn ($q) => $q->where('loyalty_points', 0))->exists())->toBeTrue();

    // at least one area with no Sales assigned (empty-state edge case)
    expect(Area::whereDoesntHave('salesProfiles')->exists())->toBeTrue();
});

it('seeds every buyer account state, each with complete store data unless that is the point', function () {
    (new AreaSeeder())->run();
    (new UserSeeder())->run();

    $profiles = \App\Models\BuyerProfile::query();
    expect((clone $profiles)->where('status', 'pending')->count())->toBe(3)
        ->and((clone $profiles)->where('status', 'rejected')->whereNotNull('rejection_reason')->count())->toBe(2)
        ->and(User::where('must_change_password', true)->whereNotNull('password_set_by')->count())->toBe(2)
        ->and((clone $profiles)->where(fn ($q) => $q->whereNull('store_photo_path')->orWhereNull('latitude'))->count())->toBe(2)
        ->and((clone $profiles)->where('is_active', false)->count())->toBe(1)
        ->and(\App\Models\SalesProfile::where('is_active', false)->count())->toBe(1)
        ->and((clone $profiles)->where('status', 'approved')->where('is_active', true)->whereNull('sales_user_id')->exists())->toBeTrue();

    $buyer1 = User::where('email', 'buyer1@naraya.test')->first();
    expect($buyer1->buyerProfile->loyalty_points)->toBe(1240)
        ->and($buyer1->buyerProfile->owner_name)->toBe('Siti Rahayu')
        ->and($buyer1->buyerProfile->phone)->toStartWith('62')
        ->and($buyer1->addresses()->where('is_default', true)->whereNotNull('lat')->exists())->toBeTrue();

    expect(UserSeeder::shoppingBuyers()->count())->toBe(48);
});
