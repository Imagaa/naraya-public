<?php

use App\Enums\Role;
use App\Livewire\Admin\Products\Form;
use App\Livewire\Admin\Products\Index;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists products and filters by search', function () {
    Product::factory()->create(['name' => 'Mie Instan Original']);
    Product::factory()->create(['name' => 'Kecap Manis']);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('Mie Instan Original')
        ->assertSee('Kecap Manis')
        ->set('search', 'Kecap')
        ->assertDontSee('Mie Instan Original')
        ->assertSee('Kecap Manis');
});

it('filters the product list by search on SKU too', function () {
    Product::factory()->create(['name' => 'Produk A', 'sku' => '8991234567890']);
    Product::factory()->create(['name' => 'Produk B', 'sku' => '8999999999999']);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('search', '8991234567890')
        ->assertSee('Produk A')
        ->assertDontSee('Produk B');
});

it('filters the product list by stock status', function () {
    Product::factory()->create(['name' => 'Habis Total', 'is_active' => true, 'stock' => 0, 'min_stock' => 5]);
    Product::factory()->create(['name' => 'Hampir Habis', 'is_active' => true, 'stock' => 3, 'min_stock' => 5]);
    Product::factory()->create(['name' => 'Stok Aman', 'is_active' => true, 'stock' => 50, 'min_stock' => 5]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('stockFilter', 'out')
        ->assertSee('Habis Total')
        ->assertDontSee('Hampir Habis')
        ->assertDontSee('Stok Aman')
        ->set('stockFilter', 'low')
        ->assertDontSee('Habis Total')
        ->assertSee('Hampir Habis')
        ->assertDontSee('Stok Aman');
});

it('creates a product with a category, structured size, and required SKU', function () {
    $category = Category::factory()->create(['name' => 'Minuman']);

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Teh Kemasan')
        ->set('sku', '8991111111111')
        ->set('categoryId', $category->id)
        ->set('sizeValue', 500)
        ->set('sizeUnit', 'ml')
        ->set('price', 4500)
        ->set('stock', 50)
        ->call('save')
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('name', 'Teh Kemasan')->firstOrFail();
    expect($product->sku)->toBe('8991111111111');
    expect($product->category_id)->toBe($category->id);
    expect($product->size_value)->toBe(500.0);
    expect($product->size_unit)->toBe('ml');
});

it('requires a SKU when creating a new product', function () {
    $category = Category::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Produk Tanpa SKU')
        ->set('sku', '')
        ->set('categoryId', $category->id)
        ->set('price', 1000)
        ->call('save')
        ->assertHasErrors('sku');

    expect(Product::where('name', 'Produk Tanpa SKU')->exists())->toBeFalse();
});

it('does not require a SKU when editing a product that never had one', function () {
    $product = Product::factory()->create(['sku' => null]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->set('name', 'Nama Baru')
        ->call('save')
        ->assertHasNoErrors('sku');

    expect($product->fresh()->name)->toBe('Nama Baru');
});

it('requires a category to save a product', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Produk Tanpa Kategori')
        ->set('sku', '8992222222222')
        ->set('price', 1000)
        ->call('save')
        ->assertHasErrors('categoryId');
});

it('creates a new category from the product form and selects it', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('newCategoryName', 'Frozen Food')
        ->set('newCategoryColor', '#3B82F6')
        ->set('newCategoryIcon', 'snowflake')
        ->call('createCategory')
        ->assertHasErrors('newCategoryIcon');

    expect(Category::where('name', 'Frozen Food')->exists())->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('newCategoryName', 'Frozen Food')
        ->set('newCategoryColor', '#3B82F6')
        ->set('newCategoryIcon', 'milk')
        ->call('createCategory');

    $category = Category::where('name', 'Frozen Food')->firstOrFail();
    expect($category->color)->toBe('#3B82F6');
    expect($category->icon)->toBe('milk');
});

it('uploads a photo when saving a product', function () {
    Storage::fake('s3');
    $category = Category::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Produk Foto')
        ->set('sku', '8993333333333')
        ->set('categoryId', $category->id)
        ->set('price', 1000)
        ->set('photo', UploadedFile::fake()->image('produk.jpg'))
        ->call('save');

    $product = Product::where('name', 'Produk Foto')->firstOrFail();
    expect($product->photo_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($product->photo_path);
});

it('prefills the name from a barcode lookup when the product is found and name is empty', function () {
    Http::fake([
        'world.openfoodfacts.org/*' => Http::response([
            'code' => '5449000000996', 'status' => 1,
            'product' => ['product_name' => 'Coca-Cola'],
        ]),
    ]);

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('barcode', '5449000000996')
        ->call('lookupBarcode')
        ->assertSet('name', 'Coca-Cola');
});

it('does not overwrite an already-typed name on barcode lookup', function () {
    Http::fake([
        'world.openfoodfacts.org/*' => Http::response([
            'code' => '5449000000996', 'status' => 1,
            'product' => ['product_name' => 'Coca-Cola'],
        ]),
    ]);

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Nama Sudah Diisi')
        ->set('barcode', '5449000000996')
        ->call('lookupBarcode')
        ->assertSet('name', 'Nama Sudah Diisi');
});

it('shows a friendly message when the barcode is not found', function () {
    Http::fake([
        'world.openfoodfacts.org/*' => Http::response(['code' => '00000000', 'status' => 0]),
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('barcode', '00000000')
        ->call('lookupBarcode');

    expect($component->get('barcodeLookupMessage'))->toContain('tidak ditemukan');
});

it('no longer offers stock adjustment from the product form', function () {
    $product = Product::factory()->create();

    expect(method_exists(Form::class, 'applyStockAdjustment'))->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->assertDontSee('Sesuaikan Stok');
});

it('blocks non-admin roles from the products index', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/products')
        ->assertStatus(403);
});
