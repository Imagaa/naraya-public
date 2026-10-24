<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\DeliveryRoute;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use App\Support\Sales\Consignments;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

// Field work with no signal. Toys, the line open on production.
beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Warung Bu Ani']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id, 'consignment_opened_at' => null]);
    $this->kartu = Product::factory()->create(['name' => 'Kartu Mainan', 'line' => 'toys', 'stock' => 120, 'price' => 12000, 'unit' => 'lembar', 'pieces_per_unit' => 12]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Toys, [$this->kartu->id => 48]);
});

function offlineSync($test, string $type, array $payload, array $extra = [])
{
    return $test->actingAs($test->sales)->post(salesUrl('/offline/sync'), $extra + [
        'uuid' => (string) Str::uuid(), 'type' => $type, 'line' => 'toys', 'recorded_at' => now()->toIso8601String(),
        'payload' => json_encode($payload),
    ], ['Accept' => 'application/json']);
}

it('gives the phone what its offline forms check against', function () {
    app(Consignments::class)->open($this->sales, $this->store->id, BusinessLine::Toys, [$this->kartu->id => 24]);

    $this->actingAs($this->sales)->withSession(['line' => 'toys'])->getJson(salesUrl('/offline/data'))->assertOk()
        ->assertJsonPath('line.value', 'toys')
        ->assertJsonPath("products.{$this->kartu->id}.ppu", 12)
        ->assertJsonPath("carried.{$this->kartu->id}", 48)
        ->assertJsonPath('stores.0.name', 'Warung Bu Ani')
        ->assertJsonPath('stores.0.opened', true)
        ->assertJsonPath("stores.0.at.{$this->kartu->id}", 24);
});

it('replays a first count, then a visit, applying an item sent twice only once', function () {
    offlineSync($this, 'awal', ['store' => $this->store->id, 'items' => [$this->kartu->id => 24]])->assertOk();

    $visit = ['uuid' => (string) Str::uuid()];
    $payload = ['store' => $this->store->id, 'remaining' => [$this->kartu->id => 4], 'titip' => [$this->kartu->id => 12], 'method' => 'cash'];
    offlineSync($this, 'titip', $payload, $visit)->assertOk()->assertJson(['message' => 'Kunjungan Warung Bu Ani terkirim.']);
    offlineSync($this, 'titip', $payload, $visit)->assertOk()->assertJson(['message' => 'Sudah terkirim.']);

    expect(SalesNote::sole())->total->toBe(20000)->source->toBe('titipan') // 20 pcs sold at Rp 1.000
        ->and(Consignments::atStore($this->store->id, BusinessLine::Toys)->all())->toBe([$this->kartu->id => 12])
        ->and(Consignments::returnsHeld($this->sales, BusinessLine::Toys)->all())->toBe([$this->kartu->id => 4])
        ->and(CarriedStock::for($this->sales, BusinessLine::Toys)->all())->toBe([$this->kartu->id => 36]);
});

it('replays a nota, a closed store and a new store', function () {
    app(Consignments::class)->open($this->sales, $this->store->id, BusinessLine::Toys, []);
    offlineSync($this, 'nota', ['store' => $this->store->id, 'items' => [$this->kartu->id => 12], 'method' => 'transfer'],
        ['photo' => UploadedFile::fake()->image('bukti.jpg')])->assertOk();
    expect(SalesNote::sole())->total->toBe(12000)->proof_path->not->toBeNull();

    $route = DeliveryRoute::create(['sales_id' => $this->sales->id, 'status' => 'active', 'date' => today()]);
    $other = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $other->id, 'sales_user_id' => $this->sales->id]);
    $stop = $route->stops()->create(['buyer_id' => $other->id, 'position' => 1, 'status' => 'pending']);
    offlineSync($this, 'tutup', ['store' => $other->id, 'note' => 'buka sore'], ['photo' => UploadedFile::fake()->image('tutup.jpg')])->assertOk();
    expect($stop->fresh())->status->toBe('closed')->closed_note->toBe('buka sore');

    offlineSync($this, 'toko', ['name' => 'Toko Baru', 'owner' => 'Pak Joko', 'phone' => '081234567890', 'address' => 'Jl. Melati 3', 'lat' => -6.2, 'lng' => 106.8],
        ['photo' => UploadedFile::fake()->image('depan.jpg')])->assertOk()->assertJson(['message' => 'Toko Toko Baru terdaftar.']);
    expect(User::where('name', 'Toko Baru')->sole()->buyerProfile)->sales_user_id->toBe($this->sales->id)->address->toBe('Jl. Melati 3');
});

it('rejects an item the books no longer allow, with a message for the phone', function () {
    app(Consignments::class)->open($this->sales, $this->store->id, BusinessLine::Toys, []);

    offlineSync($this, 'nota', ['store' => $this->store->id, 'items' => [$this->kartu->id => 60], 'method' => 'cash'])
        ->assertStatus(422)->assertJson(['message' => 'Kartu Mainan: yang Anda bawa hanya 4 lembar.']);
    offlineSync($this, 'toko', ['name' => 'X'])->assertStatus(422);

    expect(SalesNote::count())->toBe(0)->and(\Illuminate\Support\Facades\DB::table('offline_actions')->count())->toBe(0);
});
