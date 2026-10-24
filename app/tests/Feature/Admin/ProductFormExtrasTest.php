<?php

use App\Enums\Role;
use App\Livewire\Admin\Products\Form;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    Storage::fake('s3');
});

it('saves pieces per unit and the promo end date', function () {
    $product = Product::factory()->create(['price' => 10000]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->set('discountPrice', 9000)
        ->set('piecesPerUnit', 40)
        ->set('discountEndsAt', '2030-10-05T23:59')
        ->call('save')
        ->assertHasNoErrors();

    $product->refresh();
    expect($product->pieces_per_unit)->toBe(40);
    // Admin types Jakarta time (WIB, UTC+7); it is stored in UTC.
    expect($product->discount_ends_at->utc()->format('Y-m-d H:i'))->toBe('2030-10-05 16:59');
});

it('ignores the promo end date when there is no discount price', function () {
    $product = Product::factory()->create(['price' => 10000]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->set('discountEndsAt', '2030-10-05T23:59')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh()->discount_ends_at)->toBeNull();
});

it('prefills the new fields when editing', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 9000,
        'discount_ends_at' => '2030-10-05 16:59:00', 'pieces_per_unit' => 24,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->assertSet('piecesPerUnit', 24)
        ->assertSet('discountEndsAt', '2030-10-05T23:59');
});

it('uploads extra photos in order', function () {
    $product = Product::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->set('extraPhotos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $photos = $product->photos()->get();
    expect($photos)->toHaveCount(2);
    expect($photos->pluck('sort_order')->all())->toBe([1, 2]);
    Storage::disk('s3')->assertExists($photos->first()->path);
});

it('rejects more than five extra photos in total', function () {
    $product = Product::factory()->create();
    foreach (range(1, 4) as $i) {
        ProductPhoto::create(['product_id' => $product->id, 'path' => "products/{$i}.jpg", 'sort_order' => $i]);
    }

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->set('extraPhotos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->call('save')
        ->assertHasErrors('extraPhotos');

    expect($product->photos()->count())->toBe(4);
});

it('removes an extra photo and its file', function () {
    $product = Product::factory()->create();
    Storage::disk('s3')->put('products/x.jpg', 'img');
    $photo = ProductPhoto::create(['product_id' => $product->id, 'path' => 'products/x.jpg', 'sort_order' => 1]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->call('removePhoto', $photo->id);

    expect(ProductPhoto::find($photo->id))->toBeNull();
    Storage::disk('s3')->assertMissing('products/x.jpg');
});

it('moves an extra photo one place earlier', function () {
    $product = Product::factory()->create();
    $a = ProductPhoto::create(['product_id' => $product->id, 'path' => 'products/a.jpg', 'sort_order' => 1]);
    $b = ProductPhoto::create(['product_id' => $product->id, 'path' => 'products/b.jpg', 'sort_order' => 2]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->call('movePhoto', $b->id, -1);

    expect($product->photos()->pluck('id')->all())->toBe([$b->id, $a->id]);
});

it('removes a product\'s link to a shared demo photo but keeps the file other products use', function () {
    $product = Product::factory()->create();
    Storage::disk('s3')->put('seed/products/extra-a.jpg', 'img');
    $photo = ProductPhoto::create(['product_id' => $product->id, 'path' => 'seed/products/extra-a.jpg', 'sort_order' => 1]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['product' => $product])
        ->call('removePhoto', $photo->id);

    expect(ProductPhoto::find($photo->id))->toBeNull();
    Storage::disk('s3')->assertExists('seed/products/extra-a.jpg');
});
