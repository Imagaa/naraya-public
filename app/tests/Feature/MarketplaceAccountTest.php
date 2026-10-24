<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Account;
use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('redirects guests to login', function () {
    $this->get('http://'.config('app.domain').'/akun')
        ->assertRedirect(route('login'));
});

it('shows a read-only profile summary before editing', function () {
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Old Name', 'email' => 'old@naraya.test']);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->assertSet('editingProfile', false)
        ->assertSee('Old Name')
        ->assertSee('old@naraya.test')
        ->assertDontSee('Simpan Profil');
});

it('opens the edit form only after Ubah Profil is clicked', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->call('showEditProfile')
        ->assertSet('editingProfile', true)
        ->assertSee('Simpan Profil');
});

it('discards unsaved profile edits on cancel', function () {
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Old Name']);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->call('showEditProfile')
        ->set('name', 'Unsaved Name')
        ->call('cancelEditProfile')
        ->assertSet('editingProfile', false)
        ->assertSet('name', 'Old Name');

    expect($buyer->fresh()->name)->toBe('Old Name');
});

it('updates the buyer name and phone and returns to the summary view', function () {
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Old Name']);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->call('showEditProfile')
        ->set('name', 'New Name')
        ->set('phone', '08123456789')
        ->call('saveProfile')
        ->assertSet('editingProfile', false)
        ->assertSet('statusMessage', 'Profil berhasil disimpan.');

    expect($buyer->fresh()->name)->toBe('New Name');
    expect($buyer->fresh()->buyerProfile->phone)->toBe('08123456789');
});

it('adds a new address and marks the first one default', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->call('showAddAddressForm')
        ->set('newProvinceId', '33')
        ->set('newProvinceName', 'JAWA TENGAH')
        ->set('newCityId', '3374')
        ->set('newCityName', 'KOTA SEMARANG')
        ->set('newDistrictId', '3374010')
        ->set('newDistrictName', 'MIJEN')
        ->set('newVillageId', '3374010001')
        ->set('newVillageName', 'CANGKIRAN')
        ->set('newDetail', 'Jl. Contoh No. 1')
        ->set('newLat', -6.9667)
        ->set('newLng', 110.4167)
        ->call('saveAddress')
        ->assertSet('addingAddress', false);

    $address = $buyer->addresses()->sole();
    expect($address->is_default)->toBeTrue();
    expect($address->city_name)->toBe('KOTA SEMARANG');
});

it('edits an existing address', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $address = Address::factory()->for($buyer)->create(['detail' => 'Old detail']);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->call('editAddress', $address->id)
        ->assertSet('newDetail', 'Old detail')
        ->set('newDetail', 'New detail')
        ->call('saveAddress');

    expect($address->fresh()->detail)->toBe('New detail');
});

it('deletes an address and promotes another to default', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $default = Address::factory()->for($buyer)->create(['is_default' => true]);
    $other = Address::factory()->for($buyer)->create(['is_default' => false]);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->call('deleteAddress', $default->id);

    expect(Address::find($default->id))->toBeNull();
    expect($other->fresh()->is_default)->toBeTrue();
});

it('switches the default address', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $first = Address::factory()->for($buyer)->create(['is_default' => true]);
    $second = Address::factory()->for($buyer)->create(['is_default' => false]);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->call('setDefaultAddress', $second->id);

    expect($first->fresh()->is_default)->toBeFalse();
    expect($second->fresh()->is_default)->toBeTrue();
});

it('changes the password when the current password is correct', function () {
    $buyer = User::factory()->create(['role' => Role::User, 'password' => Hash::make('old-password')]);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->set('currentPassword', 'old-password')
        ->set('newPassword', 'new-password-123')
        ->set('newPassword_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertSet('statusMessage', 'Kata sandi berhasil diubah.');

    expect(Hash::check('new-password-123', $buyer->fresh()->password))->toBeTrue();
});

it('rejects a password change with the wrong current password', function () {
    $buyer = User::factory()->create(['role' => Role::User, 'password' => Hash::make('old-password')]);

    Livewire::actingAs($buyer)
        ->test(Account::class)
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'new-password-123')
        ->set('newPassword_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasErrors('currentPassword');

    expect(Hash::check('old-password', $buyer->fresh()->password))->toBeTrue();
});
