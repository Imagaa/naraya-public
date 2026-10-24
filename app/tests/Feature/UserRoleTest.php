<?php

use App\Enums\Role;
use App\Models\User;

it('casts role to the Role enum and exposes role-check helpers', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    $gudang = User::factory()->create(['role' => Role::Gudang]);
    $buyer = User::factory()->create(['role' => Role::User]);

    expect($admin->role)->toBe(Role::Admin);
    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isSales())->toBeFalse();

    expect($sales->isSales())->toBeTrue();
    expect($gudang->isGudang())->toBeTrue();
    expect($buyer->isUser())->toBeTrue();
});
