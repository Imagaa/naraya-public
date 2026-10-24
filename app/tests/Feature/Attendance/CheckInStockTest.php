<?php

use App\Enums\Role;
use App\Enums\StockTransferKind;
use App\Livewire\StoreAccount;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\BuyerProfile;
use App\Models\DeliveryRoute;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Stock\CarriedStock;
use App\Support\BusinessDay;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Check-in = stock taken + the day's route, any time of day.
beforeEach(function () {
    Storage::fake('s3');
    $this->travelTo(Carbon::parse('2026-10-05 14:20', 'Asia/Jakarta')); // a Monday afternoon: no set hours
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    AttendanceLocation::create(['nama' => 'Gudang', 'lat' => -6.2, 'lng' => 106.8, 'radius_m' => 100]);
    $this->mie = Product::factory()->create(['name' => 'Mie', 'stock' => 50]);
    $this->kopi = Product::factory()->create(['name' => 'Kopi', 'stock' => 5]);
});

function checkIn($test, array $overrides = [])
{
    $token = $test->actingAs($test->sales)->postJson(salesUrl('/absensi/sesi'))->json('token');

    return $test->actingAs($test->sales)->postJson(salesUrl('/absensi/submit'), $overrides + [
        'tipe' => 'masuk', 'token' => $token, 'latitude' => -6.2, 'longitude' => 106.8, 'akurasi' => 10,
        'foto' => UploadedFile::fake()->image('a.jpg', 640, 480), 'idempotency_key' => (string) Str::uuid(),
    ]);
}

it('records the stock taken from the warehouse with the check-in', function () {
    checkIn($this, ['items' => json_encode([$this->mie->id => 12, $this->kopi->id => 0])])->assertCreated()->assertJson(['hasil' => 'Tercatat']);

    expect($this->mie->fresh()->stock)->toBe(38)
        ->and(CarriedStock::for($this->sales, \App\Enums\BusinessLine::Naraya)->all())->toBe([$this->mie->id => 12])
        ->and(StockTransfer::sole()->kind)->toBe(StockTransferKind::Ambil);
});

it('records nothing when the warehouse no longer has the quantity', function () {
    checkIn($this, ['items' => json_encode([$this->kopi->id => 6])])->assertStatus(422);

    expect(Attendance::count())->toBe(0)->and(StockTransfer::count())->toBe(0)->and($this->kopi->fresh()->stock)->toBe(5)
        ->and(Storage::disk('s3')->allFiles('attendances'))->toBe([]);
});

it('starts no route at check-in: routes are their own menu', function () {
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id]);

    checkIn($this)->assertCreated();
    expect(DeliveryRoute::count())->toBe(0);
});

it('refuses check-out while cash is held, then gives back all carried stock and the store returns', function () {
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id]);
    checkIn($this, ['items' => json_encode([$this->mie->id => 12])])->assertCreated();
    app(\App\Support\Sales\Consignments::class)->visit($this->sales, $store->id, \App\Enums\BusinessLine::Naraya, [], [$this->mie->id => 5], \App\Enums\NotePaymentMethod::Cash);
    \App\Models\ConsignmentMove::create(['buyer_id' => $store->id, 'product_id' => $this->kopi->id, 'line' => 'naraya', 'sales_id' => $this->sales->id, 'kind' => 'retur', 'quantity' => -2]);
    app(\App\Support\Sales\SalesNotes::class)->create($this->sales, \App\Enums\BusinessLine::Naraya, $store->id, [$this->mie->id => 3], \App\Enums\NotePaymentMethod::Cash);

    $this->travelTo(Carbon::parse('2026-10-05 17:00', 'Asia/Jakarta'));
    checkIn($this, ['tipe' => 'pulang'])->assertStatus(422)->assertJsonFragment(['message' => 'Setor dulu cash Rp '.number_format(\App\Models\SalesNote::sole()->total, 0, ',', '.').' sebelum absen pulang.']);

    \Livewire\Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\Deposits\Index::class)->call('submitDeposit')->assertHasNoErrors();
    checkIn($this, ['tipe' => 'pulang'])->assertCreated();

    expect(CarriedStock::for($this->sales, \App\Enums\BusinessLine::Naraya)->all())->toBe([])
        ->and($this->mie->fresh()->stock)->toBe(50 - 12 + 4)                  // 12 taken, 5 left at the store, 3 sold, 4 back
        ->and(\App\Support\Sales\Consignments::returnsHeld($this->sales, \App\Enums\BusinessLine::Naraya)->all())->toBe([])
        ->and(StockTransfer::where('kind', StockTransferKind::ReturToko)->sole()->items->sole()->quantity)->toBe(2)
        ->and($this->kopi->fresh()->stock)->toBe(5);                         // store returns are not sellable stock
});

it('walks the check-out sheet: deposit first, then the goods, then the photo', function () {
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id]);
    app(\App\Support\Stock\StockTransfers::class)->pickup($this->sales, \App\Enums\BusinessLine::Naraya, [$this->mie->id => 4]);
    app(\App\Support\Sales\SalesNotes::class)->create($this->sales, \App\Enums\BusinessLine::Naraya, $store->id, [$this->mie->id => 1], \App\Enums\NotePaymentMethod::Cash);

    $sheet = \Livewire\Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\CheckOut::class)
        ->assertSet('step', 'setor')->assertSee('setorkan semua cash')
        ->call('next')->assertHasErrors('cash')->assertSet('step', 'setor');
    \Livewire\Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\Deposits\Index::class)->call('submitDeposit');
    $sheet->call('next')->assertSet('step', 'barang')->assertSee('Sisa bawaan')->assertSee('Mie')
        ->call('next')->assertSet('step', 'foto')->assertSee('absenCamera', false);
});

it('lets only admin reissue a store login; sales still create one', function () {
    $store = User::factory()->create(['role' => Role::User, 'email' => 'toko@t.test', 'password' => Hash::make('rahasia123')]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id]);

    Livewire::actingAs($this->sales)->test(StoreAccount::class, ['storeId' => $store->id])
        ->assertDontSee('Reset kata sandi')->assertSee('Minta admin')
        ->call('reissue')->assertForbidden();
    expect(Hash::check('rahasia123', $store->fresh()->password))->toBeTrue();
});
