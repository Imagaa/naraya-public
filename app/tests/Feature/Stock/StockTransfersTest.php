<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Enums\StockTransferKind;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->mie = Product::factory()->create(['name' => 'Mie', 'stock' => 10, 'price' => 3000]);
    $this->kopi = Product::factory()->create(['name' => 'Kopi', 'stock' => 5, 'price' => 2000]);
    $this->toy = Product::factory()->create(['name' => 'Mobil', 'stock' => 4, 'price' => 50000, 'line' => BusinessLine::Toys]);
    $this->transfers = app(StockTransfers::class);
});

it('receives goods into the warehouse with a traceable stock movement', function () {
    $t = $this->transfers->receive(BusinessLine::Naraya, [$this->mie->id => 20], $this->admin, 'SJ-001 dari PT Sumber');

    expect($t->kind)->toBe(StockTransferKind::Masuk)
        ->and($t->items->first()->quantity)->toBe(20)
        ->and($t->items->first()->price)->toBe(3000)
        ->and($this->mie->fresh()->stock)->toBe(30)
        ->and($this->mie->stockMovements()->latest('id')->first()->reference)->toBe("transfer:{$t->id}");
});

it('lets a sales pick up goods up to the warehouse stock and carry them', function () {
    $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 4, $this->kopi->id => 5]);

    expect($this->mie->fresh()->stock)->toBe(6)->and($this->kopi->fresh()->stock)->toBe(0)
        ->and(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->mie->id => 4, $this->kopi->id => 5]);
});

it('rejects the whole pickup when one item is over the warehouse stock', function () {
    expect(fn () => $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 4, $this->kopi->id => 6]))
        ->toThrow(ValidationException::class);

    expect($this->mie->fresh()->stock)->toBe(10)->and($this->kopi->fresh()->stock)->toBe(5)
        ->and(StockTransfer::count())->toBe(0)
        ->and(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([]);
});

it('rejects products from another line and non-positive quantities', function () {
    expect(fn () => $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->toy->id => 1]))->toThrow(ValidationException::class);
    expect(fn () => $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 0]))->toThrow(ValidationException::class);
    expect(fn () => $this->transfers->pickup($this->sales, BusinessLine::Naraya, []))->toThrow(ValidationException::class);
});

it('keeps carried stock per line', function () {
    $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 2]);
    $this->transfers->pickup($this->sales, BusinessLine::Toys, [$this->toy->id => 1]);

    expect(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->mie->id => 2])
        ->and(CarriedStock::for($this->sales, BusinessLine::Toys)->all())->toBe([$this->toy->id => 1]);
});

it('returns carried goods to the warehouse, capped at what is carried', function () {
    $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 4]);

    expect(fn () => $this->transfers->returnGoods($this->sales, BusinessLine::Naraya, [$this->mie->id => 5]))->toThrow(ValidationException::class);

    $this->transfers->returnGoods($this->sales, BusinessLine::Naraya, [$this->mie->id => 3]);
    expect($this->mie->fresh()->stock)->toBe(9)
        ->and(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->mie->id => 1]);
});

it('records a stock check shortfall without touching the warehouse, and refuses a count above the record', function () {
    $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 4, $this->kopi->id => 2]);

    expect(fn () => $this->transfers->stockCheck($this->sales, BusinessLine::Naraya, [$this->mie->id => 5], $this->admin))
        ->toThrow(ValidationException::class);

    $t = $this->transfers->stockCheck($this->sales, BusinessLine::Naraya, [$this->mie->id => 3, $this->kopi->id => 2], $this->admin);

    expect($t->kind)->toBe(StockTransferKind::Opname)
        ->and($t->items->pluck('quantity', 'product_id')->all())->toBe([$this->mie->id => 1])
        ->and($this->mie->fresh()->stock)->toBe(6)
        ->and(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->mie->id => 3, $this->kopi->id => 2]);
});

it('records no stock check transfer when everything matches', function () {
    $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 4]);

    expect($this->transfers->stockCheck($this->sales, BusinessLine::Naraya, [$this->mie->id => 4], $this->admin))->toBeNull()
        ->and(StockTransfer::where('kind', StockTransferKind::Opname)->count())->toBe(0);
});

it('refuses a second write while one for the same sales is still running', function () {
    $lock = Cache::lock('stock-sales:'.$this->sales->id, 15);
    $lock->get();

    expect(fn () => $this->transfers->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 1]))
        ->toThrow(ValidationException::class, 'masih diproses');
    $lock->release();
});
