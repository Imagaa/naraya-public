<?php

use App\Enums\Role;
use App\Livewire\Admin\Sales\Index as AdminSalesIndex;
use App\Livewire\Sales\Account;
use App\Models\SalesProfile;
use App\Models\User;
use App\Notifications\SalesNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function accountSales(array $profile = []): User
{
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Santoso', 'email' => 'budi'.uniqid().'@naraya.test', 'password' => Hash::make('lamaSekali1')]);
    SalesProfile::factory()->for($sales)->create(array_merge(['phone' => '081200000001', 'photo_path' => null], $profile));

    return $sales;
}

it('shows the account page with name and email read-only', function () {
    $this->actingAs(accountSales())->get(salesUrl('/lainnya?buka=akun'))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('@naraya.test')
        ->assertSee('Foto profil')
        ->assertSee('Nomor WhatsApp')
        ->assertSee('Ganti kata sandi')
        ->assertDontSee('wire:model="name"', false);
});

it('replaces the profile photo under a new path and removes the old file', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('sales-photos/old.jpg', 'x');
    $sales = accountSales(['photo_path' => 'sales-photos/old.jpg']);

    Livewire::actingAs($sales)->test(Account::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
        ->call('savePhoto')
        ->assertHasNoErrors();

    $path = $sales->salesProfile->fresh()->photo_path;
    expect($path)->not->toBe('sales-photos/old.jpg')->toStartWith('sales-photos/');
    Storage::disk('s3')->assertExists($path);
    Storage::disk('s3')->assertMissing('sales-photos/old.jpg');
});

it('rejects a non-image photo', function () {
    Storage::fake('s3');

    Livewire::actingAs(accountSales())->test(Account::class)
        ->set('photo', UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'))
        ->call('savePhoto')
        ->assertHasErrors('photo');
});

it('keeps the old WhatsApp number until admin approves the new one', function () {
    $sales = accountSales();

    Livewire::actingAs($sales)->test(Account::class)
        ->set('phone', '0813 9999 0000')
        ->call('requestPhoneChange')
        ->assertHasNoErrors()
        ->assertSee('Menunggu persetujuan admin');

    $profile = $sales->salesProfile->fresh();
    expect($profile->phone)->toBe('081200000001')
        ->and($profile->pending_phone)->toBe('081399990000')
        ->and($profile->pending_phone_requested_at)->not->toBeNull();
});

it('ignores a request for the number already in use', function () {
    $sales = accountSales();

    Livewire::actingAs($sales)->test(Account::class)
        ->set('phone', '+62 812-0000-0001')
        ->call('requestPhoneChange')
        ->assertHasErrors('phone');

    expect($sales->salesProfile->fresh()->pending_phone)->toBeNull();
});

it('lets admin approve or reject a number change, and tells the sales user', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => Role::Admin]);
    $approved = accountSales(['pending_phone' => '081399990000', 'pending_phone_requested_at' => now()]);
    $rejected = accountSales(['pending_phone' => '081377770000', 'pending_phone_requested_at' => now()]);

    Livewire::actingAs($admin)->test(AdminSalesIndex::class)
        ->assertSee('081399990000')
        ->call('approvePhone', $approved->id)
        ->call('rejectPhone', $rejected->id);

    expect($approved->salesProfile->fresh()->only('phone', 'pending_phone'))->toBe(['phone' => '081399990000', 'pending_phone' => null])
        ->and($rejected->salesProfile->fresh()->only('phone', 'pending_phone'))->toBe(['phone' => '081200000001', 'pending_phone' => null]);
    Notification::assertSentTo($approved, SalesNotice::class, fn (SalesNotice $n) => $n->title === 'Nomor WhatsApp disetujui');
    Notification::assertSentTo($rejected, SalesNotice::class, fn (SalesNotice $n) => $n->title === 'Perubahan nomor ditolak');
});

it('counts pending number changes on the admin Sales menu', function () {
    accountSales(['pending_phone' => '081399990000', 'pending_phone_requested_at' => now()]);

    $this->actingAs(User::factory()->create(['role' => Role::Admin]))
        ->get('http://'.config('app.admin_domain').'/sales')
        ->assertSee('<span class="adm-nav-badge">1</span>', false);
});

it('changes the password only with the current one', function () {
    $sales = accountSales();

    Livewire::actingAs($sales)->test(Account::class)
        ->set('currentPassword', 'salah')->set('newPassword', 'baruSekali2')->set('newPassword_confirmation', 'baruSekali2')
        ->call('updatePassword')
        ->assertHasErrors('currentPassword');

    Livewire::actingAs($sales)->test(Account::class)
        ->set('currentPassword', 'lamaSekali1')->set('newPassword', 'baruSekali2')->set('newPassword_confirmation', 'baruSekali2')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('baruSekali2', $sales->fresh()->password))->toBeTrue();
});

it('shows the sales photo wherever that sales person appears', function () {
    Storage::fake('s3');
    $sales = accountSales(['photo_path' => 'sales-photos/budi.jpg']);
    $url = Storage::disk('s3')->url('sales-photos/budi.jpg');

    // Lainnya header in the sales portal
    $this->actingAs($sales)->get(salesUrl('/lainnya'))->assertSee($url, false)->assertSee("open('akun')", false);

    // the buyer's "Sales Anda" card
    $this->flushSession();
    $buyer = User::factory()->create(['role' => Role::User]);
    $buyer->buyerProfile()->create(['sales_user_id' => $sales->id, 'status' => 'approved', 'store_photo_path' => 'stores/x.jpg', 'latitude' => -6.2, 'longitude' => 106.8]); // a buyer past the onboarding gates
    $this->actingAs($buyer)->get('http://'.config('app.domain').'/akun')->assertSee($url, false);

    // admin's sales list (fresh session: the previous identity's password hash would log admin out)
    $this->flushSession();
    $this->actingAs(User::factory()->create(['role' => Role::Admin]))
        ->get('http://'.config('app.admin_domain').'/sales')->assertSee($url, false);
});

it('keeps a shared demo photo when a sales replaces it (other sales still use it)', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('seed/sales-profiles/1.jpg', 'x');
    $sales = accountSales(['photo_path' => 'seed/sales-profiles/1.jpg']);

    Livewire::actingAs($sales)->test(Account::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
        ->call('savePhoto')
        ->assertHasNoErrors();

    Storage::disk('s3')->assertExists('seed/sales-profiles/1.jpg');
});
