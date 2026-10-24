<?php

use App\Enums\BuyerStatus;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\User;

it('defaults existing and staff-created buyers to approved', function () {
    $profile = BuyerProfile::factory()->create();
    expect($profile->fresh()->status)->toBe(BuyerStatus::Approved);
});

it('stores the onboarding fields', function () {
    $setter = User::factory()->create(['role' => Role::Admin]);
    $user = User::factory()->create(['role' => Role::User, 'must_change_password' => true, 'password_set_by' => $setter->id]);
    $profile = BuyerProfile::factory()->create([
        'user_id' => $user->id, 'status' => BuyerStatus::Rejected, 'rejection_reason' => 'Di luar area',
        'owner_name' => 'Pak Darto', 'store_photo_path' => 'stores/a.jpg',
    ]);

    expect($user->fresh()->must_change_password)->toBeTrue()
        ->and($user->fresh()->passwordSetBy->is($setter))->toBeTrue()
        ->and($profile->fresh()->rejection_reason)->toBe('Di luar area')
        ->and(BuyerStatus::Pending->label())->toBe('Menunggu persetujuan');
});

it('keeps barcodes unique', function () {
    Product::factory()->create(['barcode' => '8996001600269']);
    expect(fn () => Product::factory()->create(['barcode' => '8996001600269']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('exposes the staff domain', function () {
    expect(config('app.staff_domain'))->toBe('staf.'.config('app.domain'));
});

it('saves an optional barcode from the admin product form', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $product = Product::factory()->create();

    \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\Products\Form::class, ['product' => $product])
        ->set('barcode', '8996001600146')->call('save')->assertHasNoErrors();

    expect($product->fresh()->barcode)->toBe('8996001600146');
});
