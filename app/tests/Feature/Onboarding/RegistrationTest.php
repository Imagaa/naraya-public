<?php

use App\Enums\BuyerStatus;
use App\Enums\Role;
use App\Livewire\Admin\Buyers\Form as AdminBuyerForm;
use App\Livewire\Auth\Register;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('s3'));

function filledRegistration(string $email = 'baru@t.test')
{
    return Livewire::test(Register::class)->set('method', 'email')
        ->set('storeName', 'Toko Berkah')->set('ownerName', 'Bu Ani')->set('phone', '0813-1111-2222')
        ->set('email', $email)->set('password', 'rahasia123')->set('password_confirmation', 'rahasia123')
        ->set('newProvinceId', '31')->set('newProvinceName', 'DKI JAKARTA')
        ->set('newCityId', '3171')->set('newCityName', 'KOTA JAKARTA SELATAN')
        ->set('newDistrictId', '3171010')->set('newDistrictName', 'JAGAKARSA')
        ->set('newVillageId', '3171010001')->set('newVillageName', 'CIPEDAK')
        ->set('newDetail', 'Jl. Mawar 3')->set('newLat', -6.35)->set('newLng', 106.81)
        ->set('storePhoto', UploadedFile::fake()->image('depan.jpg'));
}

it('requires every field', function (string $field) {
    filledRegistration()->set($field, in_array($field, ['storePhoto', 'newLat'], true) ? null : '')->call('register')->assertHasErrors([$field]);
    expect(User::count())->toBe(0);
})->with(['storeName', 'ownerName', 'phone', 'email', 'password', 'newDetail', 'newVillageId', 'newLat', 'storePhoto']);

it('creates a pending buyer with derived area, default address and photo, without logging in', function () {
    $area = Area::factory()->create(['lat' => -6.34, 'lng' => 106.82]);
    filledRegistration()->call('register')->assertSee('Pendaftaran diterima. Kami akan meninjau data toko Anda dan mengabari lewat WhatsApp/email.');

    $user = User::where('email', 'baru@t.test')->firstOrFail();
    $profile = $user->buyerProfile;
    expect($user->role)->toBe(Role::User)
        ->and($user->name)->toBe('Toko Berkah')
        ->and($profile->status)->toBe(BuyerStatus::Pending)
        ->and($profile->owner_name)->toBe('Bu Ani')
        ->and($profile->area_id)->toBe($area->id)
        ->and($profile->sales_user_id)->toBeNull()
        ->and($profile->phone)->toBe('6281311112222')
        ->and($profile->hasCompleteStoreData())->toBeTrue()
        ->and($user->addresses()->where('is_default', true)->whereNotNull('lat')->exists())->toBeTrue();
    Storage::disk('s3')->assertExists($profile->store_photo_path);
    $this->assertGuest();
});

it('answers an existing email exactly like a new one and creates nothing', function () {
    $staff = User::factory()->create(['role' => Role::Admin, 'email' => 'ada@t.test']);
    $existing = filledRegistration('ada@t.test')->call('register')->html();
    $fresh = filledRegistration('belum@t.test')->call('register')->html();

    expect(User::where('email', 'ada@t.test')->count())->toBe(1)
        ->and($staff->fresh()->role)->toBe(Role::Admin)
        ->and(Storage::disk('s3')->allFiles('stores'))->toHaveCount(1);

    $strip = fn ($html) => preg_replace('/wire:(snapshot|id|key)="[^"]*"/', '', $html);
    expect($strip($existing))->toBe($strip($fresh));
});

it('rate-limits registration per IP', function () {
    foreach (range(1, 5) as $i) {
        filledRegistration("r{$i}@t.test")->call('register');
    }
    filledRegistration('r6@t.test')->call('register')->assertHasErrors(['email']);
    expect(User::where('email', 'r6@t.test')->exists())->toBeFalse();
});

it('shows pending and rejected buyers their status page and blocks shopping', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'status' => BuyerStatus::Pending]);
    $this->actingAs($buyer)->get('http://'.config('app.domain').'/checkout')->assertRedirect(route('onboarding.status'));
    $this->get(route('onboarding.status'))->assertOk()->assertSee('Akun Anda sedang ditinjau');

    $buyer->buyerProfile->update(['status' => BuyerStatus::Rejected, 'rejection_reason' => 'Di luar area layanan']);
    $this->get(route('onboarding.status'))->assertSee('Pendaftaran tidak disetujui')->assertSee('Di luar area layanan');

    $buyer->buyerProfile->update(['status' => BuyerStatus::Approved]);
    $this->get(route('onboarding.status'))->assertRedirect('/');
});

it('lets admin approve with the area sales or an override, and notifies the buyer', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $area = Area::factory()->create();
    $areaSales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $areaSales->id, 'area_id' => $area->id, 'is_active' => true]);
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'area_id' => $area->id, 'status' => BuyerStatus::Pending, 'sales_user_id' => null]);

    Livewire::actingAs($admin)->test(AdminBuyerForm::class, ['buyer' => $buyer])->assertSee('Persetujuan pendaftaran')->call('approve');
    expect($buyer->buyerProfile->fresh()->status)->toBe(BuyerStatus::Approved)
        ->and($buyer->buyerProfile->fresh()->sales_user_id)->toBe($areaSales->id)
        ->and($buyer->notifications()->first()->data['body'])->toContain('disetujui');

    $other = User::factory()->create(['role' => Role::Sales]);
    $buyer->buyerProfile->fresh()->update(['status' => BuyerStatus::Pending]);
    Livewire::actingAs($admin)->test(AdminBuyerForm::class, ['buyer' => $buyer])->set('approveSalesId', $other->id)->call('approve');
    expect($buyer->buyerProfile->fresh()->sales_user_id)->toBe($other->id);
});

it('approves without a sales when the area has none', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'area_id' => Area::factory()->create()->id, 'status' => BuyerStatus::Pending, 'sales_user_id' => null]);

    Livewire::actingAs($admin)->test(AdminBuyerForm::class, ['buyer' => $buyer])->assertSee('Belum ada sales aktif di area ini')->call('approve');
    expect($buyer->buyerProfile->fresh()->status)->toBe(BuyerStatus::Approved)
        ->and($buyer->buyerProfile->fresh()->sales_user_id)->toBeNull();
});

it('requires a reason to reject', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'status' => BuyerStatus::Pending]);

    Livewire::actingAs($admin)->test(AdminBuyerForm::class, ['buyer' => $buyer])->call('reject')->assertHasErrors(['rejectionReason']);
    Livewire::actingAs($admin)->test(AdminBuyerForm::class, ['buyer' => $buyer])->set('rejectionReason', 'Data tidak valid')->call('reject');
    expect($buyer->buyerProfile->fresh()->status)->toBe(BuyerStatus::Rejected)
        ->and($buyer->buyerProfile->fresh()->rejection_reason)->toBe('Data tidak valid');
});

it('filters pending registrations and badges the menu in admin', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    BuyerProfile::factory()->count(2)->create(['status' => BuyerStatus::Pending]);
    BuyerProfile::factory()->create(['user_id' => User::factory()->create(['role' => Role::User, 'name' => 'Toko Sudah Aktif'])->id]);

    $this->actingAs($admin)->get('http://'.config('app.admin_domain').'/buyers?status=pending')
        ->assertSee('Menunggu persetujuan')->assertDontSee('Toko Sudah Aktif')
        ->assertSeeHtml('<span class="adm-nav-badge">2</span>');
});

it('shows Masuk and Daftar to guests and links registration from the login page', function () {
    $this->blade('<x-mp.header />')->assertSee('Masuk')->assertSee('Daftar')->assertSee(route('register'), false);
    $this->get(route('login'))->assertSee('Masuk ke akun toko Anda')->assertSee('Daftar sekarang');
    $this->get(route('register'))->assertOk()->assertSee('Foto depan warung');
});
