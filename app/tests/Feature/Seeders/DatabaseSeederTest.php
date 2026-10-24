<?php

use App\Enums\CashDepositStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Area;
use App\Models\CashBookEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds a fully populated, edge-case-covered database in one call', function () {
    (new DatabaseSeeder())->run();

    expect(Area::count())->toBe(10);
    expect(User::where('role', Role::Admin)->exists())->toBeTrue();
    expect(User::where('email', 'admin@naraya.test')->exists())->toBeTrue();
    expect(Product::count())->toBeGreaterThanOrEqual(240);
    expect(Order::count())->toBeGreaterThanOrEqual(300);
    expect(Order::where('status', OrderStatus::Cancelled)->exists())->toBeTrue();
    expect(\App\Models\CashDeposit::where('status', CashDepositStatus::Disputed)->exists())->toBeTrue();
    expect(Product::where('stock', 0)->exists())->toBeTrue();
    expect(CashBookEntry::count())->toBeGreaterThan(30);

    // buyer1 has enough notifications for the bell badge, both groups and paging
    $buyer = User::where('email', 'buyer1@naraya.test')->first();
    expect($buyer->unreadNotifications()->count())->toBeGreaterThan(9)
        ->and($buyer->readNotifications()->count())->toBeGreaterThan(0)
        ->and($buyer->notifications()->count())->toBeGreaterThan(20)
        ->and($buyer->notifications()->where('created_at', '<', today())->exists())->toBeTrue()
        ->and(\App\Models\StockAlert::count())->toBeGreaterThanOrEqual(3);
});
