<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchHistory;
use App\Models\User;

beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => Role::User]);
});

it('keeps a buyer\'s searches newest first, tidied and without duplicates', function () {
    SearchHistory::remember($this->buyer, 'Kopi');
    $this->travel(1)->minutes();
    SearchHistory::remember($this->buyer, '  indomie   goreng ');
    $this->travel(1)->minutes();
    SearchHistory::remember($this->buyer, 'kopi');

    expect(SearchHistory::recent($this->buyer))->toBe(['kopi', 'indomie goreng']);
});

it('keeps only the latest eight searches', function () {
    foreach (range(1, 10) as $i) {
        SearchHistory::remember($this->buyer, "cari {$i}");
        $this->travel(1)->minutes();
    }

    expect(SearchHistory::recent($this->buyer))->toHaveCount(8)
        ->and(SearchHistory::recent($this->buyer)[0])->toBe('cari 10')
        ->and(SearchHistory::where('user_id', $this->buyer->id)->count())->toBe(8);
});

it('ignores blank searches', function () {
    SearchHistory::remember($this->buyer, '   ');

    expect(SearchHistory::recent($this->buyer))->toBe([]);
});

it('lists terms several buyers searched recently that still find products', function () {
    $category = Category::factory()->create(['name' => 'Bumbu Dapur']);
    Product::factory()->create(['name' => 'Kecap Manis Bango', 'category_id' => $category->id]);
    $others = User::factory()->count(2)->create(['role' => Role::User]);

    foreach ($others as $user) {
        SearchHistory::remember($user, 'kecap');
        SearchHistory::remember($user, 'barang hilang');
    }
    SearchHistory::remember($this->buyer, 'sekali saja');

    // "barang hilang" finds nothing, "sekali saja" only one buyer: the rest is topped up with category names
    expect(SearchHistory::popular())->toBe(['kecap', 'Bumbu Dapur']);
});

it('drops searches older than thirty days from popular', function () {
    Category::factory()->create(['name' => 'Bumbu Dapur']);
    Product::factory()->create(['name' => 'Kecap Manis Bango']);
    foreach (User::factory()->count(2)->create(['role' => Role::User]) as $user) {
        SearchHistory::remember($user, 'kecap');
    }

    $this->travel(31)->days();

    expect(SearchHistory::popular())->not->toContain('kecap');
});
