<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Livewire\Admin\Buyers\Index;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\User;
use App\Support\Sales\Consignments;
use Livewire\Livewire;

// Admin "Cek stok toko": our goods at a store and what they are worth to the sales person and to the admin.
it('shows the goods at a store with their value at the price and at the sales deposit price', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $store = User::factory()->create(['role' => Role::User, 'name' => 'Warung Bu Lis']);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $sales->id, 'consignment_opened_at' => null]);
    $toy = Product::factory()->create(['name' => 'Paket Mainan', 'line' => 'toys', 'price' => 112500, 'deposit_price' => 90000, 'unit' => 'lembar', 'pieces_per_unit' => 75]);
    app(Consignments::class)->open($sales, $store->id, BusinessLine::Toys, [$toy->id => 150]);

    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(Index::class)
        ->assertSee('Cek stok')
        ->call('showStock', $store->id)->assertDispatched('store-stock-open')
        ->assertSee('Stok di toko · Warung Bu Lis')->assertSee('2 lembar')
        ->assertSee('Rp 225.000')   // 2 lembar to the sales person
        ->assertSee('Rp 180.000');  // 2 lembar to the admin
});
