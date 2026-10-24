<?php

use App\Enums\BusinessLine;
use App\Models\CashBookClosing;
use App\Models\Category;
use App\Models\Product;

it('labels the two business lines', function () {
    expect(BusinessLine::Naraya->label())->toBe('Naraya')
        ->and(BusinessLine::Toys->label())->toBe('Naraya Toys')
        ->and(BusinessLine::cases())->toHaveCount(2);
});

it('puts existing and new records in the Naraya line unless told otherwise', function () {
    $product = Product::factory()->create();

    expect($product->fresh()->line)->toBe(BusinessLine::Naraya);
});

it('filters products and categories by line', function () {
    $naraya = Product::factory()->create(['name' => 'Mie Goreng']);
    $toys = Product::factory()->create(['name' => 'Mobil Remote', 'line' => BusinessLine::Toys]);

    expect(Product::forLine(BusinessLine::Toys)->pluck('id')->all())->toBe([$toys->id])
        ->and(Product::forLine(BusinessLine::Naraya)->pluck('id')->all())->toBe([$naraya->id]);
});

it('allows the same category name and the same closing month once per line', function () {
    Category::create(['name' => 'Lainnya', 'color' => 'neutral', 'line' => BusinessLine::Naraya]);
    Category::create(['name' => 'Lainnya', 'color' => 'neutral', 'line' => BusinessLine::Toys]);

    CashBookClosing::create(['month' => '2026-09-01', 'line' => BusinessLine::Naraya, 'opening_balance' => 0, 'total_in' => 0, 'total_out' => 0, 'closing_balance' => 0, 'breakdown' => [], 'closed_at' => now()]);
    CashBookClosing::create(['month' => '2026-09-01', 'line' => BusinessLine::Toys, 'opening_balance' => 0, 'total_in' => 0, 'total_out' => 0, 'closing_balance' => 0, 'breakdown' => [], 'closed_at' => now()]);

    expect(Category::where('name', 'Lainnya')->count())->toBe(2)
        ->and(CashBookClosing::whereDate('month', '2026-09-01')->count())->toBe(2);
});
