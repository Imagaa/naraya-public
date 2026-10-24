<?php

use App\Enums\AttendanceType;
use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Sales\Home;
use App\Models\Attendance;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\SalesTrackPoint;
use App\Models\User;
use App\Support\Sales\SalesNotes;
use App\Support\Sales\Shift;
use App\Support\Stock\StockTransfers;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 08:00', 'Asia/Jakarta'));
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->masuk = Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => now(), 'latitude' => -6.2, 'longitude' => 106.8]);
});

/** A point $minutes after 08:00 WIB, $metresNorth north of the start. */
function pt(int $minutes, float $metresNorth, int $acc = 10): array
{
    return ['lat' => -6.2 + $metresNorth / 111320, 'lng' => 106.8, 'acc' => $acc, 't' => Carbon::parse('2026-10-05 08:00', 'Asia/Jakarta')->addMinutes($minutes)->getTimestampMs()];
}

it('takes GPS points only during an open shift, drops rough ones, and keeps a re-sent point once', function () {
    $this->travelTo(Carbon::parse('2026-10-05 09:00', 'Asia/Jakarta'));
    $send = fn (array $points) => $this->actingAs($this->sales)->postJson(salesUrl('/lokasi'), ['points' => $points]);

    $send([pt(5, 0), pt(10, 500), pt(15, 900, 250)])->assertOk()->assertJson(['tracking' => true, 'stored' => 2]);
    $send([pt(10, 500)])->assertOk()->assertJson(['stored' => 0]); // the offline queue sent it again
    expect(SalesTrackPoint::count())->toBe(2);

    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Pulang, 'created_at' => now()]);
    $send([pt(55, 2000)])->assertStatus(409)->assertJson(['tracking' => false]);
});

it('adds up the road travelled, ignoring jitter and bad jumps, and shows the gaps', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00', 'Asia/Jakarta'));
    Shift::record($this->masuk, [
        pt(1, 0), pt(2, 5),          // standing: 5 m of jitter
        pt(5, 1000),                 // 1 km
        pt(6, 60000),                // 59 km in a minute: a bad fix
        pt(8, 2000),                 // 1 km more
        pt(40, 2000),                // 32 minutes with nothing before this: a gap
    ]);

    $recap = Shift::recap($this->masuk);
    expect($recap['km'])->toBe(2.0)->and($recap['open'])->toBeTrue()->and($recap['minutes'])->toBe(120)
        ->and(count($recap['gaps']))->toBe(2); // 08:08–08:40, and 08:40–now
});

it('recaps the stores visited in the shift with what each bought', function () {
    $this->travelTo(Carbon::parse('2026-10-05 11:00', 'Asia/Jakarta'));
    $store = User::factory()->create(['role' => Role::User, 'name' => 'Warung Bu Ani']);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id]);
    $mie = Product::factory()->create(['stock' => 20, 'price' => 3000]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$mie->id => 10]);
    app(SalesNotes::class)->create($this->sales, BusinessLine::Naraya, $store->id, [$mie->id => 4], NotePaymentMethod::Cash);

    $recap = Shift::recap($this->masuk);
    expect($recap['stores'])->toHaveCount(1)
        ->and($recap['stores'][0])->toMatchArray(['name' => 'Warung Bu Ani', 'notes' => 1, 'total' => 12000])
        ->and($recap['total'])->toBe(12000)->and($recap['cash'])->toBe(12000);

    Livewire::actingAs($this->sales)->test(Home::class)->assertSee('Perjalanan hari ini')->assertSee('1 toko');
});

it('turns GPS on in the layout only while checked in', function () {
    $this->actingAs($this->sales)->get(salesUrl('/'))->assertSee('data-shift="'.$this->masuk->id.'"', false)->assertSee('id="sales-gps"', false);

    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Pulang, 'created_at' => now()]);
    $this->actingAs($this->sales)->get(salesUrl('/'))->assertDontSee('data-shift=', false)->assertSee('Rekap hari ini');
});
