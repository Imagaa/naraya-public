<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Orders;
use App\Livewire\Marketplace\ProductsIndex;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/** Every horizontal scroller goes through .hscroll (themed fades + arrows), never a bare browser scrollbar. */
it('declares horizontal overflow and hidden scrollbars only on .hscroll', function () {
    foreach (File::files(resource_path('css/marketplace')) as $file) {
        $css = preg_replace('#/\*.*?\*/#s', '', $file->getContents());
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as [, $selector, $body]) {
            $selector = trim($selector);
            if (preg_match('/overflow-x:\s*auto|overflow:\s*auto/', $body)) {
                expect(str_contains($selector, '.hscroll'))->toBeTrue("{$file->getFilename()}: {$selector} scrolls horizontally without .hscroll");
            }
            if (str_contains($selector, '::-webkit-scrollbar') && str_contains($body, 'display: none')) {
                expect(str_contains($selector, '.hscroll'))->toBeTrue("{$file->getFilename()}: {$selector} hides a scrollbar outside .hscroll");
            }
        }
    }
});

it('themes the vertical scrollbar in the marketplace stylesheet', function () {
    $base = File::get(resource_path('css/marketplace/base.css'));
    expect($base)->toContain('scrollbar-width: thin')->toContain('::-webkit-scrollbar-thumb');
});

it('gives the header category row the themed scroller with arrows', function () {
    $category = Category::factory()->create(['name' => 'Kopi & Teh']);
    Product::factory()->create(['category_id' => $category->id]);

    $this->blade('<x-mp.header />')
        ->assertSee('x-data="hscroll"', false)
        ->assertSee('catnav hscroll', false)
        ->assertSee('aria-label="Geser kategori ke kiri"', false)
        ->assertSee('aria-label="Geser kategori ke kanan"', false)
        // a bare .next collides with the rewards card (display: grid) and shows the arrow on phones
        ->assertSee('hscroll-btn hscroll-next', false)
        ->assertDontSee('hscroll-btn next', false);
});

it('uses the themed scroller on the product list chips and the order filters', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);
    Livewire::test(ProductsIndex::class)->assertSeeHtml('hscroll cat-chips');

    Livewire::actingAs(User::factory()->create(['role' => Role::User]))->test(Orders::class)->assertSeeHtml('hscroll filters');
});

it('uses the themed scroller on rails', function () {
    $this->blade('<x-mp.rail title="Rak" :products="$p" key-prefix="t" />', ['p' => Product::factory()->count(3)->create()])
        ->assertSee('rail hscroll', false)
        ->assertSee('x-data="hscroll"', false);
});
