<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ToySeeder;
use Illuminate\Support\Facades\Storage;

it('seeds the Naraya Toys catalogue on the Toys line only, with photos and attribution, once', function () {
    User::factory()->create(['role' => Role::Admin]); // StockMovement needs an actor
    $manifest = json_decode(file_get_contents(database_path('seeders/data/toys.json')), true);

    (new ToySeeder())->run();
    (new ToySeeder())->run(); // safe to run again (production): adds nothing

    $toys = Product::forLine(BusinessLine::Toys)->with('category')->get();
    expect($toys)->toHaveCount(count($manifest))
        ->and(Product::forLine(BusinessLine::Naraya)->count())->toBe(0)
        ->and($toys->every(fn ($p) => $p->category->line === BusinessLine::Toys && $p->price > 0 && $p->stock > 0))->toBeTrue()
        ->and($toys->pluck('photo_path')->unique())->toHaveCount(count($manifest))
        ->and($toys->every(fn ($p) => str_contains($p->description, 'Wikimedia Commons')))->toBeTrue()
        ->and(Category::forLine(BusinessLine::Toys)->count())->toBe(collect($manifest)->pluck('category')->unique()->count());
    Storage::disk('s3')->assertExists($toys->first()->photo_path);
});
