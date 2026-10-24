<?php

use App\Enums\BusinessLine;
use App\Livewire\Marketplace\ProductShow;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// Grosir price notice
it('stamps price changes only', function () {
    $this->travelTo(Carbon::parse('2026-10-20 03:00'));
    $product = Product::factory()->create(['price' => 5000, 'line' => BusinessLine::Naraya]);
    expect($product->fresh()->price_changed_at->toDateTimeString())->toBe('2026-10-20 03:00:00');

    $this->travelTo(Carbon::parse('2026-10-21 03:00'));
    $product->update(['name' => 'Gula Pasir 1 kg']);
    expect($product->fresh()->price_changed_at->toDateTimeString())->toBe('2026-10-20 03:00:00');

    $this->travelTo(Carbon::parse('2026-10-23 03:00'));
    $product->update(['price' => 5500]);
    expect($product->fresh()->price_changed_at->toDateTimeString())->toBe('2026-10-23 03:00:00');

    $this->travelTo(Carbon::parse('2026-10-24 03:00'));
    $product->update(['discount_price' => 5200]);
    expect($product->fresh()->price_changed_at->toDateTimeString())->toBe('2026-10-24 03:00:00');
});

it('shows the notice and timestamp for grosir only', function () {
    $this->travelTo(Carbon::parse('2026-10-23 03:00')); // Jumat 10.00 WIB
    $grosir = Product::factory()->create(['line' => BusinessLine::Naraya, 'stock' => 10, 'is_active' => true]);
    $toy = Product::factory()->create(['line' => BusinessLine::Toys, 'stock' => 10, 'is_active' => true]);

    Livewire::test(ProductShow::class, ['product' => $grosir])
        ->assertSee('Harga dapat berubah sewaktu-waktu.')
        ->assertSee('Harga per Jumat, 23 Oktober 2026, 10.00 WIB');
    Livewire::test(ProductShow::class, ['product' => $toy])
        ->assertDontSee('Harga dapat berubah sewaktu-waktu.')->assertDontSee('Harga per ');
});
