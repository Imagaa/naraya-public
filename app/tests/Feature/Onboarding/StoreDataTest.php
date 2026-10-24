<?php

use App\Enums\Role;
use App\Livewire\Admin\Buyers\Form as AdminBuyerForm;
use App\Livewire\Marketplace\Onboarding\CompleteStore;
use App\Livewire\Sales\Buyers\Form as SalesBuyerForm;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('s3'));

function filledStoreForm($test)
{
    return $test->set('name', 'Toko Lancar')->set('ownerName', 'Pak Dar')
        ->set('phone', '081298765432')->set('address', 'Jl. Kenanga 2');
}

it('requires the photo and the pin in the admin form', function (string $missing) {
    $admin = User::factory()->create(['role' => Role::Admin]);
    filledStoreForm(Livewire::actingAs($admin)->test(AdminBuyerForm::class))
        ->set('lat', $missing === 'lat' ? null : -6.2)->set('lng', $missing === 'lat' ? null : 106.8)
        ->set('storePhoto', $missing === 'storePhoto' ? null : UploadedFile::fake()->image('d.jpg'))
        ->call('save')->assertHasErrors([$missing]);
    expect(User::where('role', Role::User)->count())->toBe(0);
})->with(['storePhoto', 'lat']);

it('rejects a pin outside Indonesia', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    filledStoreForm(Livewire::actingAs($admin)->test(AdminBuyerForm::class))
        ->set('lat', 48.85)->set('lng', 2.35)->set('storePhoto', UploadedFile::fake()->image('d.jpg'))
        ->call('save')->assertHasErrors(['lat', 'lng']);
});

it('derives the area and sales from the pin and stores the photo for admin-created buyers', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $near = Area::factory()->create(['lat' => -6.20, 'lng' => 106.80]);
    Area::factory()->create(['lat' => -6.90, 'lng' => 107.60]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'area_id' => $near->id, 'is_active' => true]);

    filledStoreForm(Livewire::actingAs($admin)->test(AdminBuyerForm::class))
        ->set('lat', -6.21)->set('lng', 106.81)->set('storePhoto', UploadedFile::fake()->image('d.jpg'))
        ->call('save')->assertHasNoErrors();

    $profile = BuyerProfile::latest('id')->first();
    expect($profile->area_id)->toBe($near->id)
        ->and($profile->sales_user_id)->toBe($sales->id)
        ->and($profile->owner_name)->toBe('Pak Dar')
        ->and($profile->phone)->toBe('6281298765432')
        ->and($profile->hasCompleteStoreData())->toBeTrue();
    Storage::disk('s3')->assertExists($profile->store_photo_path);
});

it('makes the sales form require photo and pin and assigns the store to that sales', function () {
    $area = Area::factory()->create(['lat' => -6.2, 'lng' => 106.8]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'area_id' => $area->id]);

    filledStoreForm(Livewire::actingAs($sales)->test(SalesBuyerForm::class))->call('save')->assertHasErrors(['storePhoto', 'lat']);

    filledStoreForm(Livewire::actingAs($sales)->test(SalesBuyerForm::class))
        ->set('lat', -6.2)->set('lng', 106.8)->set('storePhoto', UploadedFile::fake()->image('d.jpg'))
        ->call('save')->assertHasNoErrors();
    expect(BuyerProfile::latest('id')->first()->sales_user_id)->toBe($sales->id);
});

it('keeps the existing photo when editing without a new one', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'owner_name' => 'X', 'phone' => '628111', 'address' => 'A', 'store_photo_path' => 'stores/old.jpg', 'latitude' => -6.2, 'longitude' => 106.8]);

    Livewire::actingAs($admin)->test(AdminBuyerForm::class, ['buyer' => $buyer])->set('name', 'Nama Baru')->call('save')->assertHasNoErrors();
    expect($buyer->buyerProfile->fresh()->store_photo_path)->toBe('stores/old.jpg');
});

it('sends buyers with incomplete store data to the completion page, after the password gate', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'store_photo_path' => null, 'latitude' => null, 'longitude' => null]);
    $this->actingAs($buyer)->get('http://'.config('app.domain').'/')->assertRedirect(route('onboarding.store'));

    $buyer->update(['must_change_password' => true]);
    $this->get('http://'.config('app.domain').'/')->assertRedirect(route('onboarding.password'));
});

it('completes the store data and lets the buyer shop', function () {
    Area::factory()->create(['lat' => -6.2, 'lng' => 106.8]);
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'store_photo_path' => null, 'latitude' => null, 'longitude' => null]);

    Livewire::actingAs($buyer)->test(CompleteStore::class)->call('save')->assertHasErrors(['storePhoto', 'lat']);
    Livewire::actingAs($buyer)->test(CompleteStore::class)
        ->set('lat', -6.21)->set('lng', 106.81)->set('storePhoto', UploadedFile::fake()->image('d.jpg'))
        ->call('save')->assertRedirect('/');
    expect($buyer->buyerProfile->fresh()->hasCompleteStoreData())->toBeTrue();
});

it('tags incomplete stores in the admin and sales lists', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    BuyerProfile::factory()->create(['store_photo_path' => null]);
    $this->actingAs($admin)->get('http://'.config('app.admin_domain').'/buyers')->assertSee('Data toko belum lengkap');

    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $sales->id]);
    BuyerProfile::factory()->create(['sales_user_id' => $sales->id, 'latitude' => null]);
    $this->actingAs($sales)->get('http://'.config('app.sales_domain').'/')->assertSee('Data toko belum lengkap');
});
