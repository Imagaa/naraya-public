<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Reward;
use App\Models\StockMovement;
use App\Models\User;
use App\Enums\Role;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RewardSeeder;

it('seeds the open food facts catalogue with unique photos, valid barcodes and every edge case', function () {
    User::factory()->create(['role' => Role::Admin]); // StockMovement needs an actor
    (new CategorySeeder())->run();
    (new ProductSeeder())->run();

    $products = Product::all();
    expect($products->count())->toBeGreaterThanOrEqual(240)
        ->and($products->pluck('photo_path')->unique()->count())->toBe($products->count())
        ->and($products->pluck('barcode')->unique()->count())->toBe($products->count())
        ->and($products->every(fn ($p) => \App\Support\Ean13::isValid($p->barcode)))->toBeTrue()
        ->and(\App\Models\ProductPhoto::pluck('path')->intersect($products->pluck('photo_path')))->toBeEmpty()
        // the 8 food categories; Open Food Facts has almost no non-food (soap, household) items
        ->and(\App\Models\Category::has('products', '>=', 10)->count())->toBeGreaterThanOrEqual(8);

    expect(Product::where('stock', 0)->count())->toBeGreaterThanOrEqual(8)
        ->and(Product::whereColumn('stock', '<', 'min_purchase')->where('stock', '>', 0)->count())->toBeGreaterThanOrEqual(4)
        ->and(Product::where('is_active', false)->count())->toBeGreaterThanOrEqual(5)
        ->and(Product::promo()->whereNotNull('discount_ends_at')->count())->toBeGreaterThanOrEqual(6)
        ->and(Product::promo()->whereNull('discount_ends_at')->count())->toBeGreaterThanOrEqual(4)
        ->and(Product::whereNotNull('discount_price')->where('discount_ends_at', '<', now())->count())->toBeGreaterThanOrEqual(3)
        ->and(Product::whereNull('pieces_per_unit')->count())->toBeGreaterThan(0)
        ->and(StockMovement::where('type', StockMovementType::In)->count())->toBe($products->count());
});

it('seeds rewards with photos', function () {
    (new RewardSeeder())->run();

    expect(Reward::count())->toBe(10);
    expect(Reward::whereNotNull('photo_path')->count())->toBe(10);
    expect(Reward::pluck('name')->unique()->count())->toBe(10)
        ->and(Reward::pluck('photo_path')->unique()->count())->toBe(10)
        ->and(Reward::pluck('name')->all())->toBe(array_column(RewardSeeder::REWARDS, 0))
        ->and(Reward::where('stock', 0)->count())->toBe(1);
});

it('refreshes existing reward rows in place without touching their redemptions', function () {
    $old = Reward::factory()->count(2)->create(['name' => 'Fugit Mollitia']);
    $redemption = \App\Models\Redemption::factory()->create(['reward_id' => $old[0]->id]);

    $this->artisan('demo:refresh-rewards')->assertSuccessful();

    expect(Reward::count())->toBe(10)
        ->and($old[0]->fresh()->name)->toBe(RewardSeeder::REWARDS[0][0])
        ->and($redemption->fresh()->reward_id)->toBe($old[0]->id)
        ->and(Reward::where('name', 'Fugit Mollitia')->exists())->toBeFalse();
});

it('stores reward photos under name-based paths so a CDN never serves an old image', function () {
    (new RewardSeeder())->run();
    expect(Reward::where('name', 'Mug Naraya')->value('photo_path'))->toBe('seed/rewards/mug-naraya.jpg');
});
