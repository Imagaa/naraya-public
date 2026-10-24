<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CashBookEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\OrderSeeder;

beforeEach(function () {
    (new AreaSeeder())->run();
    (new UserSeeder())->run();
    (new \Database\Seeders\CategorySeeder())->run();
    User::factory()->create(['role' => Role::Admin]); // stock movement actor for ProductSeeder
    (new ProductSeeder())->run();
});

it('seeds orders across every status with realistic edge cases', function () {
    (new OrderSeeder())->run();

    expect(Order::count())->toBeGreaterThanOrEqual(300);

    foreach (OrderStatus::cases() as $status) {
        expect(Order::where('status', $status)->exists())
            ->toBeTrue("Expected at least one order with status {$status->value}");
    }

    expect(Order::whereNotNull('parent_order_id')->exists())->toBeTrue();
    expect(Order::where('credit_applied', '>', 0)->exists())->toBeTrue();
    expect(Order::where('needs_payment_review', true)->exists())->toBeTrue();
    expect(Order::whereIn('payment_method', ['gopay', 'qris'])->exists())->toBeTrue();
    expect(Order::where('payment_method', 'cash')->exists())->toBeTrue();
    expect(Order::where('payment_status', 'pending')->where('payment_expires_at', '>', now())->exists())->toBeTrue();
    expect(Order::where('payment_status', 'failed')->exists())->toBeTrue();
    expect(Order::whereHas('buyer', fn ($q) => $q->where('email', 'buyer1@naraya.test'))->count())->toBeGreaterThanOrEqual(25);
    expect(Order::whereHas('buyer.buyerProfile', fn ($q) => $q->where('status', '!=', 'approved'))->exists())->toBeFalse();

    // completed cash orders produce backdated cash-book income entries
    $completedCash = Order::where('status', OrderStatus::Completed)->where('payment_method', 'cash')->first();
    expect(CashBookEntry::where('reference', 'Order #'.$completedCash->id)->first()?->date->toDateString())
        ->toBe($completedCash->completed_at->toDateString());

    // the last seeded buyer deliberately has zero orders (empty-state edge case)
    $orderlessBuyer = UserSeeder::shoppingBuyers()->latest('id')->first();
    expect(Order::where('buyer_id', $orderlessBuyer->id)->exists())->toBeFalse();
});
