<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Livewire\Admin\Products\Form;
use App\Livewire\Admin\Products\Index;
use App\Livewire\Admin\Reports\Stok;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Admin\WorkQueues;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->naraya = Product::factory()->create(['name' => 'Mie Goreng Spesial', 'stock' => 0, 'is_active' => true]);
    $this->toys = Product::factory()->create(['name' => 'Mobil Remote Biru', 'stock' => 0, 'is_active' => true, 'line' => BusinessLine::Toys]);
});

it('lists only the active line\'s products in admin', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('Mie Goreng Spesial')->assertDontSee('Mobil Remote Biru');

    ActiveLine::set(BusinessLine::Toys);
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('Mobil Remote Biru')->assertDontSee('Mie Goreng Spesial');
});

it('creates products and categories in the active line and offers only its categories', function () {
    Category::create(['name' => 'Mi Instan', 'color' => 'accent', 'line' => BusinessLine::Naraya]);
    $toyCategory = Category::create(['name' => 'Mobil-mobilan', 'color' => 'accent', 'line' => BusinessLine::Toys]);
    ActiveLine::set(BusinessLine::Toys);

    Livewire::actingAs($this->admin)->test(Form::class)
        ->assertSee('Mobil-mobilan')->assertDontSee('Mi Instan')
        ->set('name', 'Boneka Beruang')->set('sku', 'TOY-BNK-1')->set('categoryId', $toyCategory->id)
        ->set('price', 75000)->set('stock', 5)
        ->call('save')->assertHasNoErrors();

    expect(Product::where('name', 'Boneka Beruang')->value('line'))->toBe(BusinessLine::Toys);
});

it('rejects a category from the other line on a product', function () {
    $narayaCategory = Category::create(['name' => 'Mi Instan', 'color' => 'accent', 'line' => BusinessLine::Naraya]);
    ActiveLine::set(BusinessLine::Toys);

    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('name', 'Boneka')->set('sku', 'TOY-1')->set('categoryId', $narayaCategory->id)->set('price', 1000)
        ->call('save')->assertHasErrors('categoryId');
});

it('counts stock alerts per line on the stock report and the work queue', function () {
    Livewire::actingAs($this->admin)->test(Stok::class)->assertViewHas('stockOut', 1);
    expect(WorkQueues::count('stock'))->toBe(1);

    // a line switch is always a new request (POST /lini redirects), and WorkQueues memoises per request
    \Illuminate\Support\Once::flush();
    Product::factory()->create(['stock' => 0, 'is_active' => true, 'line' => BusinessLine::Toys]);
    ActiveLine::set(BusinessLine::Toys);
    expect(WorkQueues::count('stock'))->toBe(2);
});

it('keeps each marketplace storefront to its own line, and a product link opens its own storefront', function () {
    $this->naraya->update(['stock' => 10]);
    $this->toys->update(['stock' => 10]);

    $this->get('http://'.config('app.domain').'/produk')
        ->assertSee('Mie Goreng Spesial')->assertDontSee('Mobil Remote Biru');
    $this->get('http://'.config('app.domain').'/produk/'.$this->toys->slug)->assertOk()->assertSee('Mobil Remote Biru');
    $this->get('http://'.config('app.domain').'/produk')
        ->assertSee('Mobil Remote Biru')->assertDontSee('Mie Goreng Spesial');
});
