<?php

use App\Enums\Role;
use App\Livewire\Marketplace\ProductsIndex;
use App\Livewire\Marketplace\SearchBox;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchHistory;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => Role::User]);
    $this->noodles = Category::factory()->create(['name' => 'Mie Instan']);
});

it('lists matches with photo, price and a "search all" row with the total', function () {
    Product::factory()->count(8)->sequence(fn ($s) => ['name' => 'Indomie Rasa '.$s->index])->create(['category_id' => $this->noodles->id, 'price' => 3500]);

    Livewire::test(SearchBox::class)
        ->set('q', 'indomi')
        ->assertSeeHtml('Cari semua "<b>indomi</b>"')
        ->assertSee('8 produk')
        ->assertSee('Rp 3.500')
        ->assertSeeHtml('class="sbox-ph"')
        ->assertViewHas('results', fn ($results) => $results->count() === 6);
});

it('says so when nothing matches', function () {
    Livewire::test(SearchBox::class)->set('q', 'qzxv')->assertSeeHtml('Tidak ada produk untuk "qzxv"')->assertSee('0 produk');
});

it('counts the same products as the full results page', function () {
    Product::factory()->create(['name' => 'Indomie Goreng', 'category_id' => $this->noodles->id]);
    Product::factory()->create(['name' => 'Indomilk Coklat', 'category_id' => $this->noodles->id]);
    Product::factory()->create(['name' => 'Kopi Kapal Api']);

    $total = Livewire::test(SearchBox::class)->set('q', 'indom')->viewData('total');
    $page = Livewire::withQueryParams(['q' => 'indom'])->test(ProductsIndex::class)->viewData('products');

    expect($total)->toBe(2)->and($page->total())->toBe(2);
});

it('shows recent and popular searches while the box is still empty', function () {
    SearchHistory::remember($this->buyer, 'kecap manis');
    Product::factory()->create(['category_id' => $this->noodles->id]);

    Livewire::actingAs($this->buyer)->test(SearchBox::class)
        ->assertSee('Pencarian terakhir')
        ->assertSee('kecap manis')
        ->assertSee('Pencarian populer')
        ->assertSee('Mie Instan');
});

it('lets the buyer remove one search or the whole history', function () {
    SearchHistory::remember($this->buyer, 'kecap');
    SearchHistory::remember($this->buyer, 'kopi');

    $box = Livewire::actingAs($this->buyer)->test(SearchBox::class)->call('forget', 'kecap');
    expect(SearchHistory::recent($this->buyer))->toBe(['kopi']);

    $box->call('clearHistory')->assertDontSee('Pencarian terakhir');
    expect(SearchHistory::recent($this->buyer))->toBe([]);
});

it('remembers the search when a buyer opens a result', function () {
    $product = Product::factory()->create(['name' => 'Indomie Goreng', 'category_id' => $this->noodles->id]);

    Livewire::actingAs($this->buyer)->test(SearchBox::class)
        ->set('q', 'indomi')
        ->call('visit', $product->id)
        ->assertRedirect($product->url());

    expect(SearchHistory::recent($this->buyer))->toBe(['indomi']);
});

it('remembers a search submitted to the results page', function () {
    Livewire::actingAs($this->buyer)->withQueryParams(['q' => 'Kopi Susu'])->test(ProductsIndex::class);

    expect(SearchHistory::recent($this->buyer))->toBe(['kopi susu']);
});

it('works for guests without a history', function () {
    Product::factory()->create(['category_id' => $this->noodles->id]);

    Livewire::test(SearchBox::class)->assertDontSee('Pencarian terakhir')->assertSee('Pencarian populer');
    Livewire::withQueryParams(['q' => 'kopi'])->test(ProductsIndex::class)->assertOk();
    expect(SearchHistory::count())->toBe(0);
});

it('orders the results page by relevance while searching', function () {
    Product::factory()->create(['name' => 'Bihun Goreng', 'category_id' => $this->noodles->id]);
    Product::factory()->create(['name' => 'Mie Goreng Spesial', 'category_id' => $this->noodles->id]);

    Livewire::withQueryParams(['q' => 'mie goreng'])->test(ProductsIndex::class)
        ->assertSet('sort', 'relevan')
        ->assertSeeInOrder(['Mie Goreng Spesial', 'Bihun Goreng'])
        ->assertSee('Paling sesuai');

    Livewire::withQueryParams([])->test(ProductsIndex::class)->assertDontSee('Paling sesuai');
});

it('puts the live search box in the header', function () {
    $this->blade('<x-mp.header />')->assertSee('x-data="searchBox"', false)->assertSee('role="combobox"', false);
});

it('hides the browser\'s own clear button in the search input', function () {
    expect(file_get_contents(resource_path('css/marketplace/search.css')))->toContain('.search input::-webkit-search-cancel-button { -webkit-appearance: none; display: none; }');
});
