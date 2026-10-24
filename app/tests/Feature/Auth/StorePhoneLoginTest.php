<?php

use App\Enums\BuyerStatus;
use App\Enums\Role;
use App\Livewire\Admin\Buyers\Form as AdminBuyerForm;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Marketplace\Onboarding\NewPassword;
use App\Models\BuyerProfile;
use App\Models\User;
use App\Support\DoorAuthenticator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('s3'));

function phoneStore(array $user = [], array $profile = []): User
{
    $store = User::factory()->create($user + ['role' => Role::User, 'email' => null, 'phone' => '6281311112222', 'password' => Hash::make('482916')]);
    BuyerProfile::factory()->create($profile + ['user_id' => $store->id, 'phone' => '6281311112222']);

    return $store;
}

function signUpByPhone(string $phone = '0813-1111-2222')
{
    return Livewire::test(Register::class)->set('method', 'phone')
        ->set('storeName', 'Toko Berkah')->set('ownerName', 'Bu Ani')->set('phone', $phone)
        ->set('pin', '482916')->set('pin_confirmation', '482916')
        ->set('newProvinceId', '31')->set('newProvinceName', 'DKI JAKARTA')
        ->set('newCityId', '3171')->set('newCityName', 'KOTA JAKARTA SELATAN')
        ->set('newDistrictId', '3171010')->set('newDistrictName', 'JAGAKARSA')
        ->set('newVillageId', '3171010001')->set('newVillageName', 'CIPEDAK')
        ->set('newDetail', 'Jl. Mawar 3')->set('newLat', -6.35)->set('newLng', 106.81)
        ->set('storePhoto', UploadedFile::fake()->image('depan.jpg'));
}

it('logs a store in with its phone number however it is typed, and a 6-digit PIN', function () {
    $store = phoneStore();

    Livewire::test(Login::class)->assertSet('method', 'phone')
        ->set('phone', '+62 813-1111-2222')->set('pin', '482916')->call('authenticate')
        ->assertRedirect('http://'.config('app.domain').'/');
    $this->assertAuthenticatedAs($store);
});

it('gives a wrong PIN, an unknown number and a store without a login the same error', function () {
    phoneStore();
    $noLogin = User::factory()->create(['role' => Role::User, 'email' => null, 'phone' => '6281399998888', 'password' => null]);
    BuyerProfile::factory()->create(['user_id' => $noLogin->id, 'phone' => '6281399998888']);

    foreach ([['0813-1111-2222', '111111'], ['0811-0000-0000', '482916'], ['0813-9999-8888', '000000']] as [$phone, $pin]) {
        Livewire::test(Login::class)->set('phone', $phone)->set('pin', $pin)->call('authenticate')
            ->assertHasErrors(['phone' => DoorAuthenticator::FAILED_PIN]);
    }
    $this->assertGuest();
});

it('locks a phone login after 10 wrong PINs whatever the IP, even for the right PIN', function () {
    phoneStore();
    foreach (range(1, 10) as $i) {
        try {
            DoorAuthenticator::attempt(['phone' => '6281311112222'], '000001', false, [Role::User], "ip-{$i}");
        } catch (ValidationException) {
        }
    }

    expect(fn () => DoorAuthenticator::attempt(['phone' => '6281311112222'], '482916', false, [Role::User], 'ip-new'))
        ->toThrow(ValidationException::class, 'Terlalu banyak percobaan');
    $this->assertGuest();
});

it('makes a phone store replace its temporary PIN with its own, refusing easy ones', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $store = phoneStore(['password' => Hash::make('739154'), 'must_change_password' => true, 'password_set_by' => $sales->id],
        ['store_photo_path' => 'stores/x.jpg', 'latitude' => -6.2, 'longitude' => 106.8]);

    $this->actingAs($store)->get(route('onboarding.password'))->assertOk()->assertSee('Buat PIN baru');
    $page = Livewire::actingAs($store)->test(NewPassword::class);
    $page->set('password', '111111')->set('password_confirmation', '111111')->call('save')->assertHasErrors('password');
    $page->set('password', '12ab56')->set('password_confirmation', '12ab56')->call('save')->assertHasErrors('password');
    $page->set('password', '739154')->set('password_confirmation', '739154')->call('save')->assertHasErrors('password'); // the temporary one
    $page->set('password', '205817')->set('password_confirmation', '205817')->call('save')->assertHasNoErrors();

    expect($store->fresh()->must_change_password)->toBeFalse()->and(Hash::check('205817', $store->fresh()->password))->toBeTrue()
        ->and($store->notifications()->first()->data['title'])->toBe('PIN berhasil diganti');
});

it('signs a new store up with phone + PIN, pending approval', function () {
    signUpByPhone()->call('register')->assertHasNoErrors()->assertSee('Pendaftaran diterima');

    $store = User::where('phone', '6281311112222')->firstOrFail();
    expect($store->email)->toBeNull()->and($store->usesPin())->toBeTrue()->and(Hash::check('482916', $store->password))->toBeTrue()
        ->and($store->buyerProfile->status)->toBe(BuyerStatus::Pending);
});

it('links a sign-up to the one store a sales registered with that phone, and admin approves or refuses the login', function () {
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $store = User::factory()->create(['role' => Role::User, 'name' => 'Warung Bu Ani', 'email' => null, 'password' => null]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'phone' => '6281311112222', 'sales_user_id' => $sales->id, 'address' => 'Jl. dari sales']);

    signUpByPhone()->call('register')->assertSee('Pendaftaran diterima');

    expect(User::where('role', Role::User)->count())->toBe(1);
    $store->refresh();
    expect($store->phone)->toBe('6281311112222')->and($store->name)->toBe('Warung Bu Ani')
        ->and($store->buyerProfile->status)->toBe(BuyerStatus::Pending)
        ->and($store->buyerProfile->address)->toBe('Jl. dari sales')
        ->and($store->buyerProfile->sales_user_id)->toBe($sales->id);

    $admin = User::factory()->create(['role' => Role::Admin]);
    Livewire::actingAs($admin)->test(AdminBuyerForm::class, ['buyer' => $store])
        ->assertSee('Permintaan akun')->assertSee('Budi')->call('reject');
    $store->refresh();
    expect($store->hasAccount())->toBeFalse()->and($store->phone)->toBeNull()
        ->and($store->buyerProfile->status)->toBe(BuyerStatus::Approved)->and($store->buyerProfile->sales_user_id)->toBe($sales->id);

    signUpByPhone()->call('register');
    Livewire::actingAs($admin)->test(AdminBuyerForm::class, ['buyer' => $store->fresh()])->call('approve');
    expect($store->fresh()->buyerProfile->status)->toBe(BuyerStatus::Approved)
        ->and($store->fresh()->buyerProfile->sales_user_id)->toBe($sales->id)
        ->and($store->fresh()->hasAccount())->toBeTrue();
});

it('answers a phone that already logs in like any other sign-up and changes nothing', function () {
    $store = phoneStore();
    $before = $store->password;

    signUpByPhone('0813 1111 2222')->call('register')->assertSee('Pendaftaran diterima');

    expect(User::where('role', Role::User)->count())->toBe(1)->and($store->fresh()->password)->toBe($before);
});
