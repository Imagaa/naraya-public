<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Livewire\Admin\LoyaltySettings\Edit;
use App\Models\BuyerProfile;
use App\Models\LoyaltySetting;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use Livewire\Livewire;

it('lets Admin set pcs per point and recomputes every store', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'loyalty_points' => 0]);
    $toy = Product::factory()->create(['line' => BusinessLine::Toys]);
    $note = SalesNote::create(['line' => BusinessLine::Toys, 'sales_id' => $admin->id, 'buyer_id' => $store->id, 'payment_method' => 'cash', 'source' => 'bawaan', 'total' => 0]);
    $note->items()->create(['product_id' => $toy->id, 'product_name' => $toy->name, 'quantity' => 300, 'price' => 1, 'subtotal' => 300]);

    Livewire::actingAs($admin)
        ->test(Edit::class)
        ->assertSet('pcsPerPoint', 150)
        ->assertSee('Mengubah angka ini menghitung ulang poin semua toko.')
        ->set('pcsPerPoint', 100)
        ->call('save');

    expect(LoyaltySetting::first()->pcs_per_point)->toBe(100)
        ->and($store->buyerProfile->fresh()->loyalty_points)->toBe(3);
});

it('blocks non-admin roles from the loyalty settings page', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/loyalty-settings')
        ->assertStatus(403);
});
