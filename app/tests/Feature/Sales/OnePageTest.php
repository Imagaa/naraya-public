<?php

use App\Enums\AttendanceType;
use App\Enums\CashCollectionStatus;
use App\Enums\Role;
use App\Livewire\Sales\Attendances\Index as AttendanceSheet;
use App\Livewire\Sales\Buyers\Show as StoreSheet;
use App\Livewire\Sales\Home;
use App\Livewire\Sales\More;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\BuyerProfile;
use App\Models\CashCollection;
use App\Models\Order;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

// Two pages, every other screen a sheet; light on low-end phones.
beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    SalesProfile::factory()->create(['user_id' => $this->sales->id, 'monthly_target' => 10000000]);
});

function storeFor(User $sales, string $name, array $profile = []): User
{
    $store = User::factory()->create(['role' => Role::User, 'name' => $name]);
    BuyerProfile::factory()->create($profile + ['user_id' => $store->id, 'sales_user_id' => $sales->id]);

    return $store;
}

it('has three tabs, Kerja, Rute and Lainnya, and no map bundle on every page', function () {
    $html = $this->actingAs($this->sales)->get(salesUrl('/'))->assertOk()
        ->assertSeeInOrder(['Kerja', 'Rute', 'Lainnya'])
        ->getContent();

    expect($html)->not->toContain('pinpoint-map')->toMatch('/build\/assets\/sales-[^"]+\.js/')
        ->toMatch('/build\/assets\/sales-[^"]+\.css/')->not->toMatch('/build\/assets\/app-[^"]+\.css/')
        ->toContain('class="hdr')->toContain('class="tabbar');
});

it('shows the target, the cash held and only my stores, flagging incomplete ones', function () {
    storeFor($this->sales, 'Toko Saya', ['store_photo_path' => null]);
    storeFor(User::factory()->create(['role' => Role::Sales]), 'Toko Sales Lain');
    CashCollection::factory()->create(['sales_id' => $this->sales->id, 'order_id' => Order::factory()->create()->id, 'amount' => 150000, 'status' => CashCollectionStatus::Held]);

    Livewire::actingAs($this->sales)->test(Home::class)
        ->assertViewHas('target', 10000000)->assertViewHas('cashHeld', 150000)
        ->assertSee('Toko Saya')->assertDontSee('Toko Sales Lain')
        ->assertSee('Data toko belum lengkap')->assertSee('Toko baru');
});

it('offers check-in any time, then check-out, then nothing', function () {
    $home = fn () => Livewire::actingAs($this->sales)->test(Home::class);
    $home()->assertSee('Absen masuk &amp; pilih stok', false)->assertDontSee('Absen pulang');

    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => now()]);
    $home()->assertSee('Absen pulang')->assertDontSee('pilih stok');

    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Pulang, 'created_at' => now()]);
    $home()->assertSee('Terima kasih atas kerja kerasnya! Selamat beristirahat!');
});

it('opens a task as a sheet over the page and closes it, with a message when done', function () {
    $store = storeFor($this->sales, 'Toko Maju');

    Livewire::actingAs($this->sales)->test(Home::class)
        ->call('open', 'toko', $store->id)->assertSet('sheet', 'toko')->assertSee('x-data="salesSheet"', false)->assertSeeLivewire(StoreSheet::class)
        ->call('open', 'tidak-ada')->assertSet('sheet', 'toko') // unknown sheets are ignored
        ->call('close')->assertSet('sheet', '')->assertDontSee('x-data="salesSheet"', false)
        ->call('open', 'setor')->call('done', 'Setoran diajukan.')->assertSet('sheet', '')->assertSee('Setoran diajukan.');
});

it('sends the old pages\' addresses to the matching sheet', function () {
    $store = storeFor($this->sales, 'Toko Maju');
    $this->actingAs($this->sales);

    $this->get(salesUrl('/setoran'))->assertRedirect(route('sales.home', ['buka' => 'setor']));
    $this->get(salesUrl('/toko/'.$store->id))->assertRedirect(route('sales.home', ['buka' => 'toko', 'id' => $store->id]));
    $this->get(salesUrl('/notifikasi'))->assertRedirect(route('sales.more', ['buka' => 'notifikasi']));
    $this->get(salesUrl('/akun'))->assertRedirect(route('sales.more', ['buka' => 'akun']));

    $this->get(salesUrl('/?buka=toko&id='.$store->id))->assertSee('sales-sheet', false)->assertSee('Kunjungan titip jual');
    $this->get(salesUrl('/lainnya?buka=notifikasi'))->assertSee('Belum ada notifikasi.');
});

it('keeps another sales person\'s store hidden, and buyers out', function () {
    $foreign = storeFor(User::factory()->create(['role' => Role::Sales]), 'Toko Lain');

    Livewire::actingAs($this->sales)->test(StoreSheet::class, ['buyer' => $foreign->id])->assertNotFound();
    $this->actingAs($foreign)->get(salesUrl('/'))->assertForbidden();
});

it('lists the history sheets, the app controls and logout on Lainnya', function () {
    config(['webpush.vapid.public_key' => 'BTestPublicKey']);

    $this->actingAs($this->sales)->get(salesUrl('/lainnya'))->assertOk()
        ->assertSee('Absensi &amp; libur mingguan', false)->assertSee('Nota saya')->assertSee('Setoran cash')->assertSee('Target bulanan')
        ->assertSee('<meta name="vapid-public-key" content="BTestPublicKey">', false)
        ->assertSee('id="sales-offline"', false)->assertSee('<meta name="naraya-build"', false)
        ->assertSee('id="pwa-push"', false)->assertSee('Tambah ke Layar Utama')->assertSee('Keluar');

    Livewire::actingAs($this->sales)->test(More::class)->call('open', 'absensi')->assertSee('Libur mingguan');
});

it('shows the attendance history and weekly day off without a camera', function () {
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => now()->subDay()]);

    Livewire::actingAs($this->sales)->test(AttendanceSheet::class)
        ->assertSee('Libur mingguan')->assertSee('Riwayat')->assertSee('Masuk')
        ->assertDontSee('absenCamera', false);
});

it('opens the camera in the check-out sheet, or says to ask admin when there is no location', function () {
    Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\CheckOut::class)->call('next')->assertSee('hubungi admin');

    AttendanceLocation::create(['nama' => 'Gudang', 'lat' => -6.2, 'lng' => 106.8, 'radius_m' => 100]);
    Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\CheckOut::class)->call('next')
        ->assertSee('absenCamera', false)->assertSee('Kirim Absen Pulang');
});
