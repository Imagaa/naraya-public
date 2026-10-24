<?php

use App\Enums\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->admin = User::factory()->create(['role' => Role::Admin]));

function listHead(string $html): string
{
    preg_match('/<div class="adm-list-head".*?<\/div>/s', $html, $m);

    return $m[0] ?? '';
}

it('renders on the shared list and keeps the same columns under every pill', function (string $component, ?string $method, array $keys) {
    $first = Livewire::actingAs($this->admin)->test($component);
    $html = $first->html();

    expect($html)->toMatch('/class="adm-list[" ]/')->not->toContain('<table class="table"');
    $head = listHead($html);
    expect($head)->not->toBe('');

    foreach ($method ? $keys : [] as $key) {
        expect(listHead(Livewire::actingAs($this->admin)->test($component)->call($method, $key)->html()))->toBe($head, "{$component} {$key}");
    }
})->with([
    'toko' => [\App\Livewire\Admin\Buyers\Index::class, 'setStatus', ['', 'pending', 'rejected']],
    'sales' => [\App\Livewire\Admin\Sales\Index::class, 'setFilter', ['all', 'active', 'inactive']],
    'area' => [\App\Livewire\Admin\Areas\Index::class, null, []],
    'absensi' => [\App\Livewire\Admin\Attendances\Index::class, 'setFilter', ['all', 'corrected']],
    'setoran' => [\App\Livewire\Admin\Deposits\Index::class, 'setFilter', ['all', 'pending', 'disputed', 'done']],
    'komplain' => [\App\Livewire\Admin\Complaints\Index::class, 'setFilter', ['all', 'waiting', 'done']],
    'penukaran' => [\App\Livewire\Admin\Redemptions\Index::class, 'setFilter', ['all', 'decision', 'handover', 'done']],
    'katalog hadiah' => [\App\Livewire\Admin\Rewards\Index::class, 'setFilter', ['all', 'active', 'inactive']],
    'produk' => [\App\Livewire\Admin\Products\Index::class, 'setStockFilter', ['all', 'out', 'low']],
]);

it('labels the stock number on the phone layout of Produk', function () {
    \App\Models\Product::factory()->create(['stock' => 4]);

    Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\Products\Index::class)
        ->assertSeeHtml('<span class="adm-phone-label">Stok </span>');
});
