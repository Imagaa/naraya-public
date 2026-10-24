<?php

use App\Enums\Role;
use App\Livewire\Admin\Buyers\Form as AdminBuyerForm;
use App\Livewire\Marketplace\Home;
use App\Livewire\Marketplace\Onboarding\NewPassword;
use App\Livewire\Sales\Buyers\Form as SalesBuyerForm;
use App\Livewire\StoreAccount;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\SalesProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('s3'));

function mustChangeBuyer(string $temp = 'TempPass2345'): User
{
    $admin = User::factory()->create(['role' => Role::Admin, 'name' => 'Admin Naraya']);
    $buyer = User::factory()->create(['role' => Role::User, 'password' => Hash::make($temp), 'must_change_password' => true, 'password_set_by' => $admin->id]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'store_photo_path' => 'stores/x.jpg', 'latitude' => -6.2, 'longitude' => 106.8]);

    return $buyer;
}

function fillStaffBuyerForm($test)
{
    return $test->set('name', 'Toko Maju')->set('ownerName', 'Bu Sri')
        ->set('phone', '0812-3456-7890')->set('address', 'Jl. Melati 1')
        ->set('lat', -6.21)->set('lng', 106.81)
        ->set('storePhoto', UploadedFile::fake()->image('depan.jpg'));
}

it('saves a store from admin without a login, then gives it an email login with a one-time temporary password', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    Area::factory()->create(['lat' => -6.2, 'lng' => 106.8]);

    fillStaffBuyerForm(Livewire::actingAs($admin)->test(AdminBuyerForm::class)->assertDontSeeHtml('wire:model="password"'))
        ->call('save')->assertHasNoErrors();
    $buyer = User::where('role', Role::User)->latest('id')->firstOrFail();
    expect($buyer->hasAccount())->toBeFalse();

    $panel = Livewire::actingAs($admin)->test(StoreAccount::class, ['storeId' => $buyer->id])
        ->assertSee('Belum punya akun')->call('$set', 'creating', true)
        ->set('method', 'email')->set('email', 'toko@t.test')->call('create')->assertHasNoErrors();

    $buyer->refresh();
    $temp = $panel->get('issued');
    expect($buyer->email)->toBe('toko@t.test')->and($buyer->must_change_password)->toBeTrue()
        ->and($buyer->password_set_by)->toBe($admin->id)
        ->and(Hash::check($temp, $buyer->password))->toBeTrue()
        ->and(strlen($temp))->toBe(12);
    $panel->assertSee($temp)->assertSeeHtml('https://wa.me/6281234567890?text=');
});

it('lets sales give their own store a phone login with a temporary 6-digit PIN, and nobody else\'s', function () {
    $area = Area::factory()->create(['lat' => -6.2, 'lng' => 106.8]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'area_id' => $area->id]);

    fillStaffBuyerForm(Livewire::actingAs($sales)->test(SalesBuyerForm::class))->call('save')->assertHasNoErrors();
    $buyer = User::where('role', Role::User)->latest('id')->firstOrFail();

    $panel = Livewire::actingAs($sales)->test(StoreAccount::class, ['storeId' => $buyer->id, 'open' => true])
        ->assertSet('phone', '081234567890')->call('create')->assertHasNoErrors();
    $buyer->refresh();
    expect($buyer->phone)->toBe('6281234567890')->and($buyer->usesPin())->toBeTrue()
        ->and($panel->get('issued'))->toMatch('/^\d{6}$/')
        ->and(Hash::check($panel->get('issued'), $buyer->password))->toBeTrue()
        ->and($buyer->password_set_by)->toBe($sales->id);
    $panel->assertSee('PIN sementara');

    // the login phone is unique
    $other = User::factory()->create(['role' => Role::User, 'email' => null, 'password' => null]);
    BuyerProfile::factory()->create(['user_id' => $other->id, 'sales_user_id' => $sales->id]);
    Livewire::actingAs($sales)->test(StoreAccount::class, ['storeId' => $other->id, 'open' => true])
        ->set('phone', '0812 3456 7890')->call('create')->assertHasErrors('phone');

    $stranger = User::factory()->create(['role' => Role::Sales]);
    Livewire::actingAs($stranger)->test(StoreAccount::class, ['storeId' => $buyer->id])->assertNotFound();
});

it('lets admin reissue a temporary password, which sets the flag again', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $buyer = mustChangeBuyer();
    $buyer->update(['must_change_password' => false]);

    $panel = Livewire::actingAs($admin)->test(StoreAccount::class, ['storeId' => $buyer->id])->call('reissue');

    expect($buyer->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check($panel->get('issued'), $buyer->fresh()->password))->toBeTrue();
});

it('sends a must-change buyer to the new password page from any buyer page', function () {
    $this->actingAs(mustChangeBuyer())->get('http://'.config('app.domain').'/pesanan')->assertRedirect(route('onboarding.password'));
    $this->get(route('onboarding.password'))->assertOk()->assertSee('Admin Naraya')->assertSee('Buat kata sandi baru');
});

it('blocks livewire actions while a gate is pending', function () {
    $buyer = mustChangeBuyer();
    $product = Product::factory()->create(['stock' => 10]);

    Livewire::actingAs($buyer)->test(Home::class)->call('addToCart', $product->id)->assertRedirect(route('onboarding.password'));
    expect(\App\Support\Cart::count())->toBe(0);
});

it('rejects reusing the temporary password', function () {
    Livewire::actingAs(mustChangeBuyer('TempPass2345'))->test(NewPassword::class)
        ->set('password', 'TempPass2345')->set('password_confirmation', 'TempPass2345')->call('save')
        ->assertHasErrors(['password']);
});

it('sets the new password, clears the flag, rotates remember, notifies, and logs out stale sessions', function () {
    $buyer = mustChangeBuyer();
    $oldHash = $buyer->password;
    $oldRemember = $buyer->remember_token;

    Livewire::actingAs($buyer)->test(NewPassword::class)
        ->set('password', 'PunyaSaya99')->set('password_confirmation', 'PunyaSaya99')->call('save')
        ->assertRedirect('/');

    $buyer->refresh();
    expect($buyer->must_change_password)->toBeFalse()
        ->and($buyer->password_set_by)->toBeNull()
        ->and(Hash::check('PunyaSaya99', $buyer->password))->toBeTrue()
        ->and($buyer->remember_token)->not->toBe($oldRemember)
        ->and($buyer->notifications()->first()->data['title'])->toBe('Kata sandi berhasil diganti');

    // a session still carrying the old password hash is logged out by AuthenticateSession
    $this->actingAs($buyer)->withSession(['password_hash_web' => $oldHash])
        ->get('http://'.config('app.domain').'/pesanan')->assertRedirect(route('login'));
});
