<?php

use App\Enums\AttendanceType;
use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Product;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

it('renders a product photo when photo_path is set', function () {
    $product = Product::factory()->create(['photo_path' => 'seed/products/mie-instan.jpg']);

    $this->blade('<x-mp.product-card :product="$product" />', ['product' => $product])
        ->assertSee('mie-instan.jpg', false);
});

it('renders a placeholder when a product has no photo', function () {
    $product = Product::factory()->create(['photo_path' => null]);

    $this->blade('<x-mp.product-card :product="$product" />', ['product' => $product])
        ->assertDontSee('<img', false)
        ->assertSee('ph-icon', false);
});

it('shows a reward photo link in the admin reward list when set', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    Reward::factory()->create(['photo_path' => 'seed/rewards/1.jpg']);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Rewards\Index::class)
        ->assertSee('1.jpg', false);
});

it('shows a sales photo link in the admin sales list when set', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'photo_path' => 'seed/sales-profiles/1.jpg']);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Sales\Index::class)
        ->assertSee('1.jpg', false);
});

it('shows an attendance photo link in the admin attendance list when set', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    Attendance::factory()->create(['sales_id' => $sales->id, 'foto_path' => 'seed/proof/1.jpg']);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Attendances\Index::class)
        ->assertSee('1.jpg', false);
});

it('shows the delivery-proof photo on a delivered redemption', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $redemption = Redemption::factory()->create(['status' => RedemptionStatus::Diserahkan, 'bukti_path' => 'seed/proof/1.jpg']);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Redemptions\Show::class, ['redemption' => $redemption])
        ->assertSee('1.jpg', false);
});
