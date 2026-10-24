<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Account;
use App\Livewire\Marketplace\Home;
use App\Livewire\Marketplace\Notifications;
use App\Livewire\Marketplace\Orders;
use App\Livewire\Marketplace\ProductShow;
use App\Livewire\Marketplace\ProductsIndex;
use App\Livewire\Marketplace\Rewards\Index as Rewards;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

/** The footer belongs to browsing pages only; task and account pages end at their content. */
beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => Role::User]));
});

it('shows the footer on the browsing pages', function (string $component) {
    Livewire::test($component)->assertSeeHtml('<footer class="foot">');
})->with([Home::class, ProductsIndex::class]);

it('leaves the footer off task and account pages', function (string $component) {
    Livewire::test($component)->assertDontSeeHtml('<footer class="foot">');
})->with([Orders::class, Notifications::class, Account::class, Rewards::class]);

it('leaves the footer off the product page', function () {
    Livewire::test(ProductShow::class, ['product' => Product::factory()->create()])->assertDontSeeHtml('<footer class="foot">');
});

it('marks the active tab icon in brand yellow', function () {
    $css = file_get_contents(resource_path('css/marketplace/header.css'));
    expect($css)->toContain('.tabbar a[aria-current="page"] .ico { color: var(--brand); }');
});
